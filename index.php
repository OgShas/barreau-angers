<?php

use Crawler\MyCrawler;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
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
                'Mailing City' => Dom::cssSelector('.adresse')->html(),
                'Mailing Street' => Dom::cssSelector('.adresse')->html(),
                'Mailing Postal Code' => Dom::cssSelector('.adresse')->html(),
                'Assermenté(e) en' => Dom::cssSelector('.w-50')->first()->innerText(),
                'Prestation de serment' => Dom::cssSelector('.w-50')->first()->innerText(),
                'Email' => Dom::cssSelector('.email')->first()->innerText(),
                'Phone' => Dom::cssSelector('.telephone')->first()->innerText(),
                'Mobile' => Dom::cssSelector('.telephone')->first()->innerText(),
                'Site-Web' => Dom::cssSelector('.site_web')->first()->link(),
                'specialities' => Dom::cssSelector('div:nth-child(2) > p.domaine')->text(),
                'Barreau' => 'Invalid selector',
                'country Code' => 'No Selector',
                'Mailing Country' => 'No Selector',
                'Entity' => 'No Selector',
                'Status Prospect' => 'No Selector',
                'Numéro de toque' => 'No Selector',
            ])
            ->refineOutput('specialities', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
                return str_replace(['Domaine d\'activité : ', 'Domaines d\'activités : '], '', $output);
            })
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

                $output = u($output)->after('<br>')->split(' ', 2)[0]->trim()->toString();;
                return $output;
            })
            ->refineOutput('Mailing Street', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                if ($output === '') {
                    return null;
                }
                $output = u($output)->before('<br>')->trim()->toString();;
                return $output;
            })
            ->refineOutput('Mailing City', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $output = u($output);
                $splitResult = $output->after('<br>')->split(' ', 2);

                if (array_key_exists(1, $splitResult)) {
                    $refineOutput = u($output)->after('<br>')->split(' ', 2)[1]->trim()->toString();
                    return $refineOutput;
                } else {
                    return null;
                }
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
                'Statut Prospect',
                'specialities'
            ])
    )
    ->runAndTraverse();
