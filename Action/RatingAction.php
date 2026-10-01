<?php

namespace Omnibus\Tnt\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Rate;
use Omnibus\Request\Rating;
use Omnibus\Request\Request;
use Omnibus\Tnt\Api;
use Omnibus\Tnt\Mapping;

/** ExpressConnect Pricing's priceCheck: every service on the lane, with the account's prices. */
final class RatingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Rating;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Rating);
        $s = $request->shipment;
        $pieces = '';
        foreach ($s->parcels as $p) {
            $pieces .= '<pieceLine><numberOfPieces>1</numberOfPieces><pieceMeasurements>'.($p->length && $p->width && $p->height ? '<length>'.number_format($p->length / 100, 2, '.', '').'</length><width>'.number_format($p->width / 100, 2, '.', '').'</width><height>'.number_format($p->height / 100, 2, '.', '').'</height>' : '').'<weight>'.number_format(max(0.1, $p->weight / 1000), 2, '.', '').'</weight></pieceMeasurements><pallet>false</pallet></pieceLine>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><priceRequest><appId>PC</appId><appVersion>1.0</appVersion><priceCheck>'
            .'<rateId>rate1</rateId><sender><country>'.strtoupper($s->sender->country).'</country><town>'.Api::e($s->sender->city).'</town><postcode>'.Api::e($s->sender->postcode).'</postcode></sender>'
            .'<delivery><country>'.strtoupper($s->recipient->country).'</country><town>'.Api::e($s->recipient->city).'</town><postcode>'.Api::e($s->recipient->postcode).'</postcode></delivery>'
            .'<collectionDateTime>'.($s->shippingDate ?? new \DateTimeImmutable('tomorrow 10:00'))->format('Y-m-d\TH:i:s').'</collectionDateTime>'
            .'<product><type>'.($s->option('documents') ? 'D' : 'N').'</type></product><account><accountNumber>'.Api::e($this->api->accountNumber).'</accountNumber><accountCountry>'.strtoupper($s->sender->country).'</accountCountry></account>'
            .'<currency>EUR</currency><priceBreakDown>false</priceBreakDown><consignmentDetails><totalWeight>'.number_format(max(0.1, $s->weight() / 1000), 2, '.', '').'</totalWeight><totalVolume>'.number_format(array_sum(array_map(static fn ($p) => ($p->length ?? 0) * ($p->width ?? 0) * ($p->height ?? 0) / 1e6, $s->parcels)), 3, '.', '').'</totalVolume><totalNumberOfPieces>'.\count($s->parcels).'</totalNumberOfPieces></consignmentDetails>'
            .'<pieceLines>'.$pieces.'</pieceLines></priceCheck></priceRequest>';
        $data = $this->api->call(Api::PRICING, 'xml_in', $xml);
        $rates = [];
        foreach ($data->priceResponse->ratedServices->ratedService ?? [] as $service) {
            $code = (string) $service->product->id;
            $rates[] = new Rate('tnt', $code, (string) ($service->product->productDesc ?: Mapping::SERVICES[$code] ?? 'TNT '.$code), (int) round(((float) $service->totalPrice) * 100), strtoupper((string) ($service->currency ?: 'EUR')));
        }
        usort($rates, static fn (Rate $a, Rate $b) => $a->amount <=> $b->amount);
        $request->setResult($rates);
    }
}
