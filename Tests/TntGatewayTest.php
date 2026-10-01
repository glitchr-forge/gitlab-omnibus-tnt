<?php

namespace Omnibus\Tnt\Tests;

use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Cancel;
use Omnibus\Request\Pickup;
use Omnibus\Tests\Fixtures;
use Omnibus\Tnt\TntGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TntGatewayTest extends TestCase
{
    private array $calls = [];

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = (string) $options['body'];
            $this->calls[] = [$url, urldecode($body), $options['headers']];

            return match (true) {
                str_contains($url, '/pricing/') => new MockResponse('<?xml version="1.0"?><document><priceResponse><rateId>rate1</rateId><ratedServices><ratedService><product><id>15N</id><productDesc>Express</productDesc></product><totalPrice>42.10</totalPrice><currency>EUR</currency></ratedService><ratedService><product><id>48N</id><productDesc>Economy Express</productDesc></product><totalPrice>25.00</totalPrice><currency>EUR</currency></ratedService></ratedServices></priceResponse></document>'),
                str_contains($url, '/shipping/') && str_contains($body, 'ESHIPPER') => new MockResponse('ABC123XYZ'),
                str_contains($url, '/shipping/') && str_contains($body, 'GET_RESULT') => new MockResponse('<?xml version="1.0"?><document><CREATE><CONREF>CMD-1042</CONREF><CONNUMBER>GE123456789FR</CONNUMBER><SUCCESS>Y</SUCCESS></CREATE><BOOK><CONSIGNMENT><CONREF>CMD-1042</CONREF><SUCCESS>Y</SUCCESS></CONSIGNMENT></BOOK></document>'),
                str_contains($url, '/shipping/') && str_contains($body, 'GET_LABEL') => new MockResponse('<?xml version="1.0"?><labelResponse><consignment key="1"><consignmentIdentity><consignmentNumber>GE123456789FR</consignmentNumber></consignmentIdentity></consignment></labelResponse>'),
                str_contains($url, 'track.do') => new MockResponse('<?xml version="1.0"?><TrackResponse><Consignment access="FULL"><ConsignmentNumber>GE123456789FR</ConsignmentNumber><SummaryCode>DEL</SummaryCode><StatusData><StatusCode>OK</StatusCode><StatusDescription>Shipment Delivered In Good Condition.</StatusDescription><LocalEventDate>20261002</LocalEventDate><LocalEventTime>1130</LocalEventTime><Depot>PAR</Depot><DepotName>Paris</DepotName></StatusData><StatusData><StatusCode>PU</StatusCode><StatusDescription>Shipment Collected From Sender.</StatusDescription><LocalEventDate>20261001</LocalEventDate><LocalEventTime>1700</LocalEventTime><Depot>PAR</Depot><DepotName>Paris</DepotName></StatusData></Consignment></TrackResponse>'),
                default => new MockResponse('<?xml version="1.0"?><document><errors><brokenRule><code>E999</code><description>Unknown</description></brokenRule></errors></document>'),
            };
        });

        return (new TntGatewayFactory($http))->create(['username' => 'user', 'password' => 'pass', 'account_number' => '000123456']);
    }

    public function testPricesComeCheapestFirst(): void
    {
        $rates = $this->gateway()->rate(Fixtures::shipment());
        self::assertSame(['48N', '15N'], array_map(fn ($r) => $r->service, $rates));
        self::assertSame(2500, $rates[0]->amount);
        self::assertStringContainsString('<accountNumber>000123456</accountNumber>', $this->calls[0][1]);
        self::assertStringContainsString('<totalWeight>0.80</totalWeight>', $this->calls[0][1]);
        self::assertContains('Authorization: Basic '.base64_encode('user:pass'), $this->calls[0][2]);
    }

    public function testAConsignmentIsCreatedThroughTheAccessCode(): void
    {
        $label = $this->gateway()->ship(Fixtures::shipment());
        self::assertSame('GE123456789FR', $label->trackingNumber);
        self::assertStringContainsString('GE123456789FR', (string) $label->content);
        self::assertStringContainsString('<SERVICE>15N</SERVICE>', $this->calls[0][1]);
        self::assertStringContainsString('<COMPANY>user</COMPANY><PASSWORD>pass</PASSWORD>', $this->calls[0][1]);
        self::assertSame('xml_in=GET_RESULT:ABC123XYZ', $this->calls[1][1]);
        self::assertSame('xml_in=GET_LABEL:ABC123XYZ', $this->calls[2][1]);
    }

    public function testTrackingAndWhatIsNotOffered(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('GE123456789FR', 'en');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame('Shipment Collected From Sender.', $tracking->events[0]->description);
        self::assertStringContainsString('locale="en_GB"', $this->calls[0][1]);
        self::assertFalse($gateway->supports(Pickup::class));
        self::assertFalse($gateway->supports(Cancel::class));
    }
}
