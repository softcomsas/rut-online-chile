<?php
require 'vendor/autoload.php';

$consulta = new Rodrigore\SIIChile\Consulta('7555986-0');
var_dump($consulta->sii());
