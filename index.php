<?php

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Crawler\MyCrawler;
use function Symfony\Component\String\u;

require_once __DIR__ . '/vendor/autoload.php';

$phoneNumberUtil = PhoneNumberUtil::getInstance();

(new MyCrawler())
    ->setStore(new SimpleCsvFileStore('./store', 'barreau-angers'))
    ->input('https://barreau-angers.org/annuaire-des-avocats/')
    ->addStep(Http::get())
    ->addStep(
        Html::each('.container .liste_avocats [class^="avocat_vignette"]')
            ->extract([
                'Full Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
                'First Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
                'Last Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
                'Barreau URL profile' => Dom::cssSelector('a')->first()->link(),
            ])
            ->refineOutput('First Name', function (mixed $output) {
                if (is_string($output)) {
                    return u($output)->split(' ', 2)[0]->toString();
                }

                return $output;
            })
            ->refineOutput('Last Name', function (mixed $output) {
                if (is_string($output)) {
                    return u($output)->split(' ', 2)[1]->toString();
                }

                return $output;
            })
            ->addToResult([
                'Barreau URL profile',
                'Full Name',
                'First Name',
                'Last Name',
            ])
    )
    ->addStep(
        Http::get()
            ->keepInputData()
            ->useInputKey('Barreau URL profile')
            ->outputKey('Link-Response')
    )
    ->addStep(
        Html::each('.avocat .container .row')
            ->useInputKey('Link-Response')
            ->keepInputData()
            ->extract([
                'Mailing City' => Dom::cssSelector('.adresse')->text(),
                'Mailing Street' => Dom::cssSelector('.adresse')->last()->innerText(),
                'Mailing Postal Code' => Dom::cssSelector('.adresse')->text(),
                'Assermenté(e) en' => Dom::cssSelector('.w-50')->first()->innerText(),
                'Prestation de serment' => Dom::cssSelector('.w-50')->first()->innerText(),
                'Email' => Dom::cssSelector('.email')->first()->innerText(),
                'Phone' => Dom::cssSelector('.telephone')->first()->innerText(),
                'Mobile' => Dom::cssSelector('.telephone')->first()->innerText(),
                'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
                'Barreau' => 'Invalid selector',
                'country Code' => 'No Selector',
                'Mailing Country' => 'No Selector',
                'Entity' => 'No Selector',
                'Status Prospect' => 'No Selector',
                'Numéro de toque' => 'No Selector',
            ])
            ->refineOutput('Email', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
                $output = str_replace(html_entity_decode('&nbsp;', ENT_COMPAT, ''), '', $output);
                return $output;

            })
            ->refineOutput('Mailing Postal Code', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
                preg_match_all('/\d+/', $output, $matches);

                if (!empty($matches[0])) {
                    $maxNumber = max($matches[0]);

                    return $maxNumber;
                }
                return null;
            })
            ->refineOutput('Mailing City', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                if (preg_match_all('/\b(\d+[^A-Z\d]+)?([A-Z][A-Z\s\'-]+)\b/u', $output, $matches)) {
                    $cities = end($matches[2]);

                    return $cities;
                }

                return '';
            })
            ->refineOutput('Assermenté(e) en', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
                $output = str_replace('°', '', $output);


                $output = substr($output, -4);

                return $output;
            })
            ->refineOutput('Phone', function (mixed $output) use ($phoneNumberUtil) {
                if (is_array($output)) {
                    return $output;
                }

                try {
                    $parts = preg_split('/[\/-]/', $output);
                    $result = $parts[0];

                    if ($result === '') {
                        return $output;
                    }

                    $phoneNumber = $phoneNumberUtil->parse($result, "FR");

                    return $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
                } catch (NumberParseException) {
                    dd('error with phone', $result);
                }
            })
            ->refineOutput('Mobile', function (mixed $output) use ($phoneNumberUtil) {
                if (is_array($output)) {
                    return $output;
                }

                $parts = preg_split('/[\/-]/', $output);
                if (isset($parts[1])) {
                    $result = $parts[1];

                    try {
                        $phoneNumber = $phoneNumberUtil->parse($result, "FR");

                        return $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
                    } catch (NumberParseException) {
                        dd('error with phone', $output);
                    }
                }
            })
            ->refineOutput(function (array $output) {
                $output['country code'] = 'fr';
                $output['Numéro de toque'] = null;
                $output['Mailing Country'] = 'France';
                $output['Région affiliée'] = 'Angers';
                $output['Entity'] = 'LAW-FR';
                $output['Statut Prospect'] = 'À qualifier';
                $output['Barreau'] = 'Angers';

                return $output;
            })
            ->addToResult([
                'Mailing City',
                'Mailing Street',
                'Mailing Postal Code',
                'Assermenté(e) en',
                'Prestation de serment',
                'Email',
                'Mobile',
                'Phone',
                'Site-Web',
                'Barreau',
                'country code',
                'Mailing Country',
                'Région affiliée',
                'Entity',
                'Numéro de toque',
                'Statut Prospect'

            ])
    )->runAndTraverse();