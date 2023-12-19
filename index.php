<?php

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use MyCrawler\MyCrawler;
use function Symfony\Component\String\u;


require_once  __DIR__ . '/vendor/autoload.php';
include 'src/MyCrawler.php';



(new MyCrawler())
    ->setStore(new SimpleCsvFileStore('./Store', 'barreau-angers'))
    ->input('https://barreau-angers.org/annuaire-des-avocats/')
    ->addStep(Http::get())
    ->addStep(
        Html::each('.container .liste_avocats [class^="avocat_vignette"]')
        ->extract([
            'Full Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
            'First Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
            'Last Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
            'Link' => Dom::cssSelector('a')->first()->link(),
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
                'Full Name',
                'First Name',
                'Last Name',
            ])
    )
    ->addStep(
        Http::get()
        ->keepInputData()
        ->useInputKey('Link')
        ->outputKey('Link-Response')
    )
    ->addStep(
        Html::each('.avocat .container .row')
        ->useInputKey('Link-Response')
        ->keepInputData()
    ->extract([
            'Mailing City' => Dom::cssSelector('.adresse')->text(),
            'Mailing Street' => Dom::cssSelector('.adresse')->last()->innerText(),
            'Mailing Postal Code' => Dom::cssSelector('.adresse')->text(), //format
            'Assermenté(e) en' => Dom::cssSelector('.w-50')->first()->innerText(),
            'Prestation de serment' => Dom::cssSelector('.w-50')->first()->innerText(),
            'Email' => Dom::cssSelector('.email')->first()->innerText(),
            'Phone' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Mobile' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
            'Barreau'=>Dom::cssSelector('p.w-50:nth-child(2)')->text(),
            'country Code'=> 'No Selector',
            'Mailing Country' => 'No Selector',
            'Entity'=>'No Selector',
            'Status Prospect' => 'No Selector',
            'Numéro de toque' => 'No Selector',
    ])


            //refine Mailing Postal Code
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

            //refine barreau
            ->refineOutput('Barreau', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $substring = ':';
                $position = strpos($output, $substring);

                if ($position !== false) {
                    // Extract text after "Cabinet : "
                    $result = substr($output, $position + strlen($substring));
                    return $result;
                }

                return null;
            })

            //refine mailing city
            ->refineOutput('Mailing City', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $allowedCities = ['CHOLET', 'ANGERS', 'BEAUCOUZE', 'PONTS', 'LOUROUX', 'TRELAZE', 'MORANNES', 'SEICHES', 'SEGRE', 'ERIGNE', 'TIERCE', 'LION', 'BARTHELEMY'];

                preg_match_all('/\b([A-Z]+)\b/u', $output, $matches);

                $filteredCities = array_intersect($matches[0], $allowedCities);

                return implode(' | ', $filteredCities);
            })

            //refine year
            ->refineOutput('Assermenté(e) en', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
                $output = str_replace('°', '', $output);

                // Extract the last 4 digits
                $output = substr($output, -4);

                return $output;
            })

            //Phone number first
            ->refineOutput('Phone', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $parts = preg_split('/[\/-]/', $output);
                if (isset($parts[0])) {
                    $result = $parts[0];

                    $PhoneNumber = $result;
                    $phoneUtil = PhoneNumberUtil::getInstance();

                    try {
                        $phoneNumberProto = $phoneUtil->parse($PhoneNumber, "FR");

                        $formattedPhoneNumber = $phoneUtil->format($phoneNumberProto, PhoneNumberFormat::INTERNATIONAL);

                        return $formattedPhoneNumber;
                    } catch (NumberParseException $e) {
                        return null;
                    }
                }

                return null;
            })

            //Phone number second
            ->refineOutput('Mobile', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $parts = preg_split('/[\/-]/', $output);
                if (isset($parts[1])) {
                    $result = $parts[1];

                    $PhoneNumber = $result;
                    $phoneUtil = PhoneNumberUtil::getInstance();

                    try {
                        $phoneNumberProto = $phoneUtil->parse($PhoneNumber, "FR");

                        $formattedPhoneNumber = $phoneUtil->format($phoneNumberProto, PhoneNumberFormat::INTERNATIONAL);

                        return $formattedPhoneNumber;
                    } catch (NumberParseException $e) {
                        return null;
                    }
                }

                return null;
            })

            ->refineOutput(function (array $output) {
                $output['country code'] = 'fr';
                $output['Numéro de toque'] = 'null';
                $output['Mailing Country'] = 'France';
                $output['Région affiliée'] = 'Angers';
                $output['Entity'] = 'LAW-FR';
                $output['Statut Prospect'] = 'À qualifier';

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
        'Status Prospect'

    ])
    )->runAndTraverse();