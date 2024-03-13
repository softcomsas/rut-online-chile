<?php

namespace Rodrigore\SIIChile;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class Consulta
{
    protected $rut;
    const XPATH_RAZON_SOCIAL = '//html/body/div/div[4]';
    const XPATH_ACTIVITIES   = '//html/body/div/table[1]/tr';

    public function __construct($rut)
    {
        $this->rut = new Rut($rut);
        $this->client = new Client([
            'verify' => __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
        ]);
    }

    public function sii()
    {
        return $this->parse($this->fetch());
    }

    private function fetch()
    {
        $captcha = $this->fetchCaptcha();
        $request = $this->client->post('https://zeus.sii.cl/cvc_cgi/stc/getstc', ['form_params' => [
            'RUT' => $this->rut->number,
            'DV'  => $this->rut->code,
            'PRG' => 'STC',
            'OPC' => 'NOR',
            'txt_code' => $captcha[0],
            'txt_captcha' => $captcha[1]
        ]]);
        return $request->getBody()->getContents();
    }

    private function fetchCaptcha()
    {
        $response = $this->client->post('https://zeus.sii.cl/cvc_cgi/stc/CViewCaptcha.cgi', ['form_params' => ['oper' => 0]]);
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        // Verificar si la decodificación JSON fue exitosa
        if ($json === null) {
            throw new \Exception('Error al decodificar la respuesta JSON');
        }

        // Extraer los datos necesarios del JSON
        $code = substr(base64_decode($json["txtCaptcha"]), 36, 4);
        $captcha = $json["txtCaptcha"];

        return [$code, $captcha];
    }


    private function parse($html)
    {
        $crawler = new Crawler($html);
        $razonSocial = ucwords(strtolower(trim($crawler->filterXPath(self::XPATH_RAZON_SOCIAL)->text())));

        $actividades = [];
        $crawler->filterXPath(self::XPATH_ACTIVITIES)->each(function (Crawler $node, $i) use (&$actividades) {
            if ($i > 0) {
                $actividades[] = [
                    'giro'      => $node->filterXPath('//td[1]/font')->text(),
                    'codigo'    => (int)$node->filterXPath('//td[2]/font')->text(),
                    'categoria' => $node->filterXPath('//td[3]/font')->text(),
                    'afecta'    => $node->filterXPath('//td[4]/font')->text() == 'Si'
                ];
            }
        });

        return ['razonSocial' => $razonSocial, 'actividades' => $actividades];
    }
}
