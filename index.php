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
            'Phone' => Dom::cssSelector('.telephone')->first()->innerText(),
            'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
    ])
            ->refineOutput('Phone', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $PhoneNumber = $output;
                $phoneUtil = PhoneNumberUtil::getInstance();
                try {
                    $phoneNumberProto = $phoneUtil->parse($PhoneNumber, "FR");
                    // Return the parsed phone number instead of dumping it
                    return $phoneNumberProto;
                } catch (NumberParseException $e) {
                    // Handle parsing failure if needed
                    return null; // or return an error message
                }
            })
    ->addToResult([
        'Address',
        'Restation de serment',
        'Email',
        'Phone',
        'Site-Web'
    ])
    )->runAndTraverse();