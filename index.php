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
            'Mailing City' => Dom::cssSelector('.adresse')->text(),  //format
            'Mailing Street' => Dom::cssSelector('.adresse')->last()->innerText(),
            'Mailing Postal Code' => Dom::cssSelector('.adresse')->last()->innerText(), //format
            'Assermenté(e) en' => Dom::cssSelector('.w-50')->first()->innerText(),
            'Prestation de serment' => Dom::cssSelector('.w-50')->first()->innerText(),
            'Email' => Dom::cssSelector('.email')->first()->innerText(),
            'Mobile' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Phone' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
            'Barreau'=>Dom::cssSelector('.w-50')->text(),   // format
            'country Code'=> 'No Selector',
            'Mailing Country' => 'No Selector',
            'Entity'=>'No Selector',
            'Status Prospect' => 'No Selector',
            'Numéro de toque' => 'No Selector',
    ])
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
            ->refineOutput('Mobile', function (mixed $output) {
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
            ->refineOutput('Phone', function (mixed $output) {
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
                $output['Status Prospect'] = 'Null';

                return $output;
            })

    ->addToResult([
        'Mailing City',
        'Mailing Street',
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
        'Statut Prospect',
        'Numéro de toque',
        'Status Prospect'

    ])
    )->runAndTraverse();