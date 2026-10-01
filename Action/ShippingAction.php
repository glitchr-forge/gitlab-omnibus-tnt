<?php

namespace Omnibus\Tnt\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;
use Omnibus\Tnt\Api;
use Omnibus\Tnt\Mapping;

/**
 * ExpressConnect Shipping: the consignment posted (an access code comes back),
 * then its result and label fetched with that code. The service is TNT's
 * product code (15N Express by default).
 */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $date = ($s->shippingDate ?? new \DateTimeImmutable('tomorrow'))->format('d/m/Y');
        $packages = '';
        foreach ($s->parcels as $p) {
            $packages .= '<PACKAGE><ITEMS>1</ITEMS><DESCRIPTION>'.Api::e((string) $s->option('description', 'Parcel')).'</DESCRIPTION>'.($p->length && $p->width && $p->height ? '<LENGTH>'.number_format($p->length / 100, 2, '.', '').'</LENGTH><HEIGHT>'.number_format($p->height / 100, 2, '.', '').'</HEIGHT><WIDTH>'.number_format($p->width / 100, 2, '.', '').'</WIDTH>' : '').'<WEIGHT>'.number_format(max(0.1, $p->weight / 1000), 2, '.', '').'</WEIGHT></PACKAGE>';
        }
        $international = strtoupper($s->sender->country) !== strtoupper($s->recipient->country);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><ESHIPPER><LOGIN><COMPANY>'.Api::e($this->api->username).'</COMPANY><PASSWORD>'.Api::e($this->api->password).'</PASSWORD><APPID>EC</APPID><APPVERSION>3.1</APPVERSION></LOGIN>'
            .'<CONSIGNMENTBATCH><SENDER>'.Mapping::address($s->sender).'<ACCOUNT>'.Api::e($this->api->accountNumber).'</ACCOUNT><VAT>'.Api::e((string) $s->option('sender_vat')).'</VAT>'
            .'<COLLECTION><COLLECTIONADDRESS>'.Mapping::address($s->sender).'</COLLECTIONADDRESS><SHIPDATE>'.$date.'</SHIPDATE><PREFCOLLECTTIME><FROM>10:00</FROM><TO>16:00</TO></PREFCOLLECTTIME></COLLECTION></SENDER>'
            .'<CONSIGNMENT><CONREF>'.Api::e(mb_substr((string) ($s->reference ?? 'omnibus'), 0, 24)).'</CONREF><DETAILS><RECEIVER>'.Mapping::address($s->recipient).'</RECEIVER>'
            .'<CUSTOMERREF>'.Api::e(mb_substr((string) $s->reference, 0, 24)).'</CUSTOMERREF><CONTYPE>'.($s->option('documents') ? 'D' : 'N').'</CONTYPE><PAYMENTIND>S</PAYMENTIND><ITEMS>'.\count($s->parcels).'</ITEMS><TOTALWEIGHT>'.number_format(max(0.1, $s->weight() / 1000), 2, '.', '').'</TOTALWEIGHT><TOTALVOLUME>'.number_format(array_sum(array_map(static fn ($p) => ($p->length ?? 0) * ($p->width ?? 0) * ($p->height ?? 0) / 1e6, $s->parcels)), 3, '.', '').'</TOTALVOLUME>'
            .'<CURRENCY>'.Api::e($s->parcels[0]->currency).'</CURRENCY><GOODSVALUE>'.number_format(array_sum(array_map(static fn ($p) => $p->value ?? 0, $s->parcels)) / 100, 2, '.', '').'</GOODSVALUE><INSURANCEVALUE>0</INSURANCEVALUE><INSURANCECURRENCY>EUR</INSURANCECURRENCY>'
            .'<SERVICE>'.Api::e($s->service ?? '15N').'</SERVICE><OPTION>'.Api::e((string) $s->option('option', '')).'</OPTION><DESCRIPTION>'.Api::e((string) $s->option('description', 'Parcel')).'</DESCRIPTION><DELIVERYINST>'.Api::e(mb_substr((string) $s->option('instructions', ''), 0, 60)).'</DELIVERYINST>'
            .($international ? '<CUSTOMCONTROLIN>N</CUSTOMCONTROLIN>' : '')
            .'<PACKAGE>'.$packages.'</PACKAGE></DETAILS></CONSIGNMENT></CONSIGNMENTBATCH>'
            .'<ACTIVITY><CREATE><CONREF>'.Api::e(mb_substr((string) ($s->reference ?? 'omnibus'), 0, 24)).'</CONREF></CREATE><BOOK ShowBookingRef="Y"><CONREF>'.Api::e(mb_substr((string) ($s->reference ?? 'omnibus'), 0, 24)).'</CONREF></BOOK><SHIP><CONREF>'.Api::e(mb_substr((string) ($s->reference ?? 'omnibus'), 0, 24)).'</CONREF></SHIP><PRINT><LABEL><CONREF>'.Api::e(mb_substr((string) ($s->reference ?? 'omnibus'), 0, 24)).'</CONREF></LABEL></PRINT></ACTIVITY></ESHIPPER>';
        $xml = str_replace('<PACKAGE><PACKAGE>', '<PACKAGE>', str_replace('</PACKAGE></PACKAGE>', '</PACKAGE>', $xml));
        $access = (string) $this->api->call(Api::SHIPPING, 'xml_in', $xml, false);
        if ('' === $access) {
            throw new CarrierException('tnt', 'TNT gave no access code for the consignment.');
        }
        $result = $this->api->call(Api::SHIPPING, 'xml_in', 'GET_RESULT:'.$access, false);
        $number = (string) ($result->CREATE->CONNUMBER ?? '');
        if ('' === $number) {
            throw new CarrierException('tnt', (string) ($result->CREATE->ERROR->DESCRIPTION ?? 'TNT created no consignment.'));
        }
        $content = null;
        try {
            $label = $this->api->call(Api::SHIPPING, 'xml_in', 'GET_LABEL:'.$access, false);
            $content = $label->asXML() ?: null; // TNT's label is an XML document rendered by its stylesheet
        } catch (CarrierException) {
        }
        $request->setResult(new Label('tnt', $number, $content, 'application/xml', null, 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=con&cons='.rawurlencode($number)));
    }
}
