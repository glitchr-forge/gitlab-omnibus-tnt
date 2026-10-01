<?php

namespace Omnibus\Tnt\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;
use Omnibus\Tnt\Api;
use Omnibus\Tnt\Mapping;

/** ExpressConnect Tracking: the consignment's status events, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call(Api::TRACKING, 'xml_in', '<?xml version="1.0" encoding="UTF-8"?><TrackRequest locale="'.(str_starts_with($request->locale, 'fr') ? 'fr_FR' : 'en_GB').'" version="3.1"><SearchCriteria marketType="INTERNATIONAL" originCountry="'.strtoupper((string) ($request->trackingNumber ? 'FR' : 'FR')).'"><ConsignmentNumber>'.Api::e($request->trackingNumber).'</ConsignmentNumber></SearchCriteria><LevelOfDetail><Complete originAddress="true" destinationAddress="true"/></LevelOfDetail></TrackRequest>');
        $consignment = $data->Consignment[0] ?? null;
        $events = [];
        foreach ($consignment->StatusData ?? [] as $status) {
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) $status->LocalEventDate.' '.(string) $status->LocalEventTime), Mapping::status((string) $status->StatusCode, (string) $status->StatusDescription), (string) $status->StatusDescription, trim((string) ($status->Depot ?: $status->DepotName)) ?: null, (string) $status->StatusCode ?: null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $status = $events ? $events[array_key_last($events)]->status : TrackingStatus::UNKNOWN;
        if ('DEL' === strtoupper((string) ($consignment->SummaryCode ?? ''))) {
            $status = TrackingStatus::DELIVERED;
        }
        $request->setResult(new TrackingModel('tnt', $request->trackingNumber, $status, $events));
    }
}
