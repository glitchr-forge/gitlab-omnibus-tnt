<?php

namespace Omnibus\Tnt;

use Omnibus\Model\Address;
use Omnibus\Model\TrackingStatus;

/** ExpressConnect's XML for ours. */
final class Mapping
{
    public const SERVICES = ['15N' => 'Express', '09N' => '9:00 Express', '10N' => '10:00 Express', '12N' => '12:00 Express', '48N' => 'Economy Express', '15D' => 'Express Documents', '12D' => '12:00 Express Documents'];

    public static function address(Address $a, bool $upper = true): string
    {
        return '<COMPANYNAME>'.Api::e(mb_substr($a->company ?? $a->name, 0, 30)).'</COMPANYNAME>'
            .'<STREETADDRESS1>'.Api::e(mb_substr($a->line(0), 0, 30)).'</STREETADDRESS1>'
            .('' !== $a->line(1) ? '<STREETADDRESS2>'.Api::e(mb_substr($a->line(1), 0, 30)).'</STREETADDRESS2>' : '')
            .'<CITY>'.Api::e(mb_substr($a->city, 0, 30)).'</CITY><POSTCODE>'.Api::e($a->postcode).'</POSTCODE><COUNTRY>'.strtoupper($a->country).'</COUNTRY>'
            .'<CONTACTNAME>'.Api::e(mb_substr($a->name, 0, 30)).'</CONTACTNAME>'
            .($a->phone ? '<CONTACTDIALCODE>'.Api::e(substr(preg_replace('/\D+/', '', $a->phone), 0, 3)).'</CONTACTDIALCODE><CONTACTTELEPHONE>'.Api::e(substr(preg_replace('/\D+/', '', $a->phone), 3)).'</CONTACTTELEPHONE>' : '')
            .($a->email ? '<CONTACTEMAIL>'.Api::e($a->email).'</CONTACTEMAIL>' : '');
    }

    public static function status(?string $code, ?string $description = null): TrackingStatus
    {
        $d = strtolower((string) $description);

        return match (true) {
            'OK' === $code || str_contains($d, 'delivered') => TrackingStatus::DELIVERED,
            'WC' === $code || str_contains($d, 'out for delivery') => TrackingStatus::OUT_FOR_DELIVERY,
            str_contains($d, 'held') || str_contains($d, 'collect') && str_contains($d, 'depot') => TrackingStatus::AVAILABLE_FOR_PICKUP,
            'RT' === $code || str_contains($d, 'return') => TrackingStatus::RETURNED,
            \in_array($code, ['NH', 'CA', 'MD', 'UA'], true) || str_contains($d, 'attempt') || str_contains($d, 'exception') || str_contains($d, 'incorrect') => TrackingStatus::EXCEPTION,
            'CI' === $code || str_contains($d, 'consignment information') || str_contains($d, 'shipment created') => TrackingStatus::PENDING,
            null !== $code && '' !== $code => TrackingStatus::IN_TRANSIT,
            default => TrackingStatus::UNKNOWN,
        };
    }
}
