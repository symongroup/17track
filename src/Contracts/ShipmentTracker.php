<?php

namespace SymonGroup\SeventeenTrack\Contracts;

use SymonGroup\SeventeenTrack\Components\TrackEvent;

interface ShipmentTracker
{
    public function register(string $trackNumber, string $carrier = null, string $tag = null): bool;

    public function getTrackInfo(string $trackNumber, int $carrier = null): array;

    public function getPureTrackInfo(string $trackNumber, int $carrier = null): array;

    public function getLastTrackEvent(string $trackNumber, int $carrier = null): TrackEvent;

    public function getLastTrackEventMulti(array $trackNumbers): array;

    public function changeCarrier(string $trackNumber, int $carrierNew, int $carrierOld = null): bool;

    public function stopTracking(string $trackNumber, int $carrier = null): bool;

    public function reTrack(string $trackNumber, int $carrier = null): bool;

    public function getTrackInfoMulti(array $trackNumbers): array;

    public function registerMulti(array $trackNumbers): array;

    public function stopTrackingMulti(array $trackNumbers): array;

    public function changeCarrierMulti(array $trackNumbers): array;

    public function reTrackMulti(array $trackNumbers): array;

    public function changeInfo(string $trackNumber, $items, int $carrier = null): bool;

    public function deleteTrack(string $trackNumber, int $carrier = null): bool;

    public function getQuota(): array;

    public function getTrackList(string $trackNumber = null, int $carrier = null, int $page_no = null);

    public function push(string $trackNumber, int $carrier = null): bool;

}