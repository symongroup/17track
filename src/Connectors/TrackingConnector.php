<?php

namespace SymonGroup\SeventeenTrack\Connectors;

use GuzzleHttp\Client;
use SymonGroup\SeventeenTrack\Components\Config;
use SymonGroup\SeventeenTrack\Components\TrackEvent;
use SymonGroup\SeventeenTrack\Contracts\ShipmentTracker;
use SymonGroup\SeventeenTrack\Exceptions\SeventeenTrackMethodCallException;

class TrackingConnector implements ShipmentTracker
{
    const API_VERSION = '/v2';

    const REGISTER_URI = '/register';

    const CHANGE_CARRIER_URI = '/changecarrier';

    const STOP_TRACK_URI = '/stoptrack';

    const RE_TRACK_URI = '/retrack';

    const GET_TRACK_INFO_URI = '/gettrackinfo';

    const CHANGE_INFO_URL = '/changeinfo';

    const DELETE_TRACK_URL = '/deletetrack';

    const GET_QUOTA_URL = '/getquota';

    const GET_TRACK_LIST_URL = '/gettracklist';

    const PUSH_URL = '/push';
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    /**
     * TrackingConnector constructor.
     * @param string $apiKey
     * @param string|null $host
     */
    public function __construct(string $apiKey, string $host = null)
    {
        $this->client = new Client();
        $this->config = new Config($apiKey, $host);
    }

    /**
     * @return Config
     */
    public function getConfig() : Config
    {
        return $this->config;
    }

    /**
     * @param string $trackNumber
     * @param string|null $carrier
     * @param string|null $tag
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function register(string $trackNumber, string $carrier = null, string $tag = null, $param = null): bool
    {
        $params = ['number' => $trackNumber];
        if(!empty($carrier)) {
            $params['carrier'] = $carrier;
        }
        if(!empty($tag)) {
            $params['tag'];
        }
        if(!empty($param)) {
            $params['param'] = $param;
        }
        $response = $this->registerMulti([
            $params
        ]);
        $this->checkErrors($response, self::REGISTER_URI);

        return true;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getTrackInfo(string $trackNumber, int $carrier = null): array
    {
        $trackInfo = $this->getTrackInfoMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($trackInfo, self::GET_TRACK_INFO_URI);

        return $trackInfo['data']['accepted'][0];
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getPureTrackInfo(string $trackNumber, int $carrier = null): array
    {
        $trackInfo = $this->getTrackInfoMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($trackInfo, self::GET_TRACK_INFO_URI);

        $trackInfo = $trackInfo['data']['accepted'][0];

        $this->checkEventHistory($trackInfo);

        $mergedEvents = $this->mergeCarriersEvents($trackInfo);

        return $this->collectTrackEvents($mergedEvents, $trackInfo['track']['e']);

    }

    /**
     * @param array $mergedEvents
     * @param int $commonTrackStatusCode
     * @return array
     */
    protected function collectTrackEvents(array $mergedEvents, int $commonTrackStatusCode): array
    {
        $trackEvents = [];

        foreach ($mergedEvents as $event) {
            $trackEvents[] = new TrackEvent($event['a'], $event['z'], $event['c'] . ' ' . $event['d'], $commonTrackStatusCode);
        }

        return $trackEvents;
    }

    /**
     * @param $trackInfo
     * @return array
     */
    private function mergeCarriersEvents(array $trackInfo): array
    {
        $mergedEvents = array_merge($trackInfo['track']['z1'], $trackInfo['track']['z2']);

        usort($mergedEvents, function ($itemOne, $itemSecond) {
            return strtotime($itemSecond['a']) - strtotime($itemOne['a']);
        });

        return $mergedEvents;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return TrackEvent
     * @throws SeventeenTrackMethodCallException
     */
    public function getLastTrackEvent(string $trackNumber, int $carrier = null): TrackEvent
    {
        $trackInfo = $this->getTrackInfoMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($trackInfo, self::GET_TRACK_INFO_URI);

        $trackInfo = $trackInfo['data']['accepted'][0];

        $this->checkEventHistory($trackInfo);

        $lastEvent = $trackInfo['track']['z0'];

        return new TrackEvent($lastEvent['a'], $lastEvent['z'], $lastEvent['c'] . ' ' . $lastEvent['d'], $trackInfo['track']['e']);
    }

    /**
     * Returns [trackNumber => [array of TrackEvent], ..., ]
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getLastTrackEventMulti(array $trackNumbers): array
    {
        $preparedTrackNumbers = [];
        foreach ($trackNumbers as $trackNumber) {
            $preparedTrackNumbers[] = ['number' => $trackNumber];
        }

        $tracksInfo = $this->getTrackInfoMulti($preparedTrackNumbers);

        $this->checkErrors($tracksInfo, self::GET_TRACK_INFO_URI);

        $lastTracksEvents = [];

        foreach ($tracksInfo['data']['accepted'] as $trackInfo) {
            if (!empty($trackInfo['track']['z0'])) {
                $event = $trackInfo['track']['z0'];
                $lastTracksEvents[$trackInfo['number']] = new TrackEvent(
                    $event['a'], $event['z'],
                    $event['c'] . ' ' . $event['d'],
                    $trackInfo['track']['e']);
            }
        }

        return $lastTracksEvents;
    }

    /**
     * @param string $trackNumber
     * @param int $carrierNew
     * @param int|null $carrierOld
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function changeCarrier(string $trackNumber, int $carrierNew, int $carrierOld = null): bool
    {
        $response = $this->changeCarrierMulti([[
            'number' => $trackNumber,
            'carrier_new' => $carrierNew,
            'carrier_old' => $carrierOld
        ]]);

        $this->checkErrors($response, self::CHANGE_CARRIER_URI);

        return true;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function stopTracking(string $trackNumber, int $carrier = null): bool
    {
        $response = $this->stopTrackingMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier,
        ]]);

        $this->checkErrors($response, self::STOP_TRACK_URI);

        return true;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function reTrack(string $trackNumber, int $carrier = null): bool
    {
        $response = $this->reTrackMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier,
        ]]);

        $this->checkErrors($response, self::RE_TRACK_URI);

        return true;
    }

    /**
     * @param string $trackNumber
     * @param int $carrier
     * @param object|array $items
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function changeInfo(string $trackNumber, $items, int $carrier = null): bool
    {
        $response = $this->changeInfoMulti([[
            'number' => $trackNumber,
            'items' => $items,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($response, self::CHANGE_INFO_URL);

        return true;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function deleteTrack(string $trackNumber, int $carrier = null): bool
    {
        $response = $this->deleteTrackMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($response, self::DELETE_TRACK_URL);

        return true;
    }

    /**
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getQuota(): array
    {
        $trackInfo = $this->getQuotaMulti();

        $this->checkErrors($trackInfo, self::GET_QUOTA_URL);

        return $trackInfo['data'];
    }

    /**
     * @param string|null $trackNumber
     * @param int|null $carrier
     * @param int|null $page_no
     * @return mixed
     * @throws SeventeenTrackMethodCallException
     */
    public function getTrackList(string $trackNumber = null, int $carrier = null, int $page_no = null)
    {
        $params = [];
        if (!empty($trackNumber)) {
            $params['number'] = $trackNumber;
        }
        if (!empty($carrier)) {
            $params['carrier'] = $carrier;
        }
        if (!empty($page_no)) {
            $params['page_no'] = $page_no;
        }
        $trackInfo = $this->getTrackListMulti([
            $params
        ]);

        $this->checkErrors($trackInfo, self::DELETE_TRACK_URL);

        return $trackInfo;
    }

    /**
     * @param string $trackNumber
     * @param int|null $carrier
     * @return bool
     * @throws SeventeenTrackMethodCallException
     */
    public function push(string $trackNumber, int $carrier = null): bool
    {
        $response = $this->pushMulti([[
            'number' => $trackNumber,
            'carrier' => $carrier
        ]]);

        $this->checkErrors($response, self::DELETE_TRACK_URL);

        return true;
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function registerMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::REGISTER_URI;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function stopTrackingMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::STOP_TRACK_URI;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function changeCarrierMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::CHANGE_CARRIER_URI;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getTrackInfoMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::GET_TRACK_INFO_URI;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array|mixed
     * @throws SeventeenTrackMethodCallException
     */
    public function reTrackMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::RE_TRACK_URI;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function changeInfoMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::CHANGE_INFO_URL;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function deleteTrackMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::DELETE_TRACK_URL;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getQuotaMulti(): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::GET_QUOTA_URL;

        return $this->baseRequest([], $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function getTrackListMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::GET_TRACK_LIST_URL;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @return array
     * @throws SeventeenTrackMethodCallException
     */
    public function pushMulti(array $trackNumbers): array
    {
        $url = $this->config->getHost() . self::API_VERSION . self::PUSH_URL;

        return $this->baseRequest($trackNumbers, $url);
    }

    /**
     * @param array $trackNumbers
     * @param string $url
     * @return mixed
     * @throws SeventeenTrackMethodCallException
     */
    protected function baseRequest(array $trackNumbers, string $url): array
    {
        try {

            $request = $this->client->post($url, [
                'headers' => $this->config->getHeaders(),
                'json' => $trackNumbers
            ]);

            return json_decode($request->getBody()->getContents(), true);

        } catch (\Exception $exception) {
            throw new SeventeenTrackMethodCallException($url, $exception->getMessage());
        }
    }

    /**
     * @param array $response
     * @param string $url
     * @throws SeventeenTrackMethodCallException
     */
    protected function checkErrors(array $response, string $url): void
    {
        if (!empty($response['data']['rejected'])) {
            $errorCode = $response['data']['rejected'][0]['error']['code'];
            $errorMessage = $response['data']['rejected'][0]['error']['message'];
            throw new SeventeenTrackMethodCallException($url, $errorMessage, $errorCode);
        }

        if (!empty($response['data']['errors'])) {
            $errorCode = $response['data']['errors'][0]['code'];
            $errorMessage = $response['data']['errors'][0]['message'];

            throw new SeventeenTrackMethodCallException($url, $errorMessage, $errorCode);
        }
    }

    /**
     * @param $trackInfo
     * @return mixed
     * @throws SeventeenTrackMethodCallException
     */
    protected function checkEventHistory($trackInfo)
    {
        if (empty($trackInfo['track']['z1'])) {
            throw new SeventeenTrackMethodCallException(self::GET_TRACK_INFO_URI, "Track event history not found");
        }
    }
}