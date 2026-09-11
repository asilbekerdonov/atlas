<?php

declare(strict_types=1);

namespace App\Service\Geo;

use App\Exception\AddressNotFoundException;
use App\Exception\GeocoderNotConfiguredException;
use App\Exception\GeocoderRequestFailedException;
use App\Exception\InvalidCoordinatesException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YandexGeocoderService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%yandex_geocoder_api_key%')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * @throws InvalidCoordinatesException
     * @throws GeocoderNotConfiguredException
     * @throws GeocoderRequestFailedException
     * @throws AddressNotFoundException
     */
    public function reverseGeocode(float $lat, float $lng): string
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            throw new InvalidCoordinatesException();
        }

        if ($this->apiKey === '') {
            throw new GeocoderNotConfiguredException();
        }

        try {
            $response = $this->httpClient->request('GET', 'https://geocode-maps.yandex.ru/v1', [
                'query' => [
                    'format' => 'json',
                    'apikey' => $this->apiKey,
                    'geocode' => sprintf('%.6f,%.6f', $lng, $lat),
                    'lang' => 'ru_RU',
                    'results' => '1',
                ],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new GeocoderRequestFailedException(previous: $e);
        }

        $feature = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'] ?? null;
        if (!is_array($feature)) {
            throw new AddressNotFoundException();
        }

        $address = $feature['metaDataProperty']['GeocoderMetaData']['text']
            ?? $feature['name']
            ?? null;

        if (!is_string($address) || $address === '') {
            throw new AddressNotFoundException();
        }

        return $address;
    }
}
