<?php

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use MyCrawler\MyCrawler;

require_once  __DIR__ . '/vendor/autoload.php';
include 'src/MyCrawler.php';



(new MyCrawler())
    ->setStore(new SimpleCsvFileStore('./Store', 'barreau-angers'))
    ->input('https://barreau-angers.org/annuaire-des-avocats/')
    ->addStep(Http::get())
    ->addStep(
        Html::each('.container .liste_avocats [class^="avocat_vignette"]')
        ->extract([
            'Name' => Dom::cssSelector('.infos_avocat h3')->first()->innerText(),
            'Link' => Dom::cssSelector('a')->first()->link(),
        ])->addLaterToResult()
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
            'Address' => Dom::cssSelector('.adresse')->text(),
            'Restation de serment' => Dom::cssSelector('.w-50')->first()->innerText(),
            'Email' => Dom::cssSelector('.email')->first()->innerText(),
            'Phone1' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Phone2' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
    ])

            //Phone number first
            ->refineOutput('Phone1', function (mixed $output) {
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
                        return $phoneNumberProto;
                    } catch (NumberParseException $e) {
                        return null;
                    }
                }

                return null;
            })

            //Phone number second
            ->refineOutput('Phone2', function (mixed $output) {
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
                        return $phoneNumberProto;
                    } catch (NumberParseException $e) {
                        return null;
                    }
                }

                return null;
            })

    ->addToResult([
        'Address',
        'Restation de serment',
        'Email',
        'Phone1',
        'Phone2',
        'Site-Web'
    ])
    )->runAndTraverse();