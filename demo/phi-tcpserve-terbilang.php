<?php

# requires PEAR extension Net_Server

require_once "Net/Server.php";
require_once "Net/Server/Handler.php";

function kekata($x) {
  $x = abs($x);
  $angka = array("", "satu", "dua", "tiga", "empat", "lima",
                 "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");

  $temp = "";
  if ($x <12) {
    $temp = " ". $angka[$x];
  } else if ($x <20) {
    $temp = kekata($x - 10). " belas";
  } else if ($x <100) {
    $temp = kekata($x/10)." puluh". kekata($x % 10);
  } else if ($x <200) {
    $temp = " seratus" . kekata($x - 100);
  } else if ($x <1000) {
    $temp = kekata($x/100) . " ratus" . kekata($x % 100);
  } else if ($x <2000) {
    $temp = " seribu" . kekata($x - 1000);
  } else if ($x <1000000) {
    $temp = kekata($x/1000) . " ribu" . kekata($x % 1000);
  } else if ($x <1000000000) {
    $temp = kekata($x/1000000) . " juta" . kekata($x % 1000000);
  } else if ($x <1000000000000) {
    $temp = kekata($x/1000000000) . " milyar" . kekata(fmod($x,1000000000));
  } else if ($x <1000000000000000) {
    $temp = kekata($x/1000000000000) . " trilyun" . kekata(fmod($x,1000000000000));
  }
  return $temp;
}

$SPEC['terbilang'] = array(
                           "v" => 1.1,
                           "summary" => 'Convert number to Indonesian words',
                           "args" => array(
                                           "n" => array(
                                                        "schema" => array("int", array("req"=>1)), # normalize manually ATM
                                                        "summary" => "The number to convert",
                                                        "req" => 1,
                                                        "pos" => 0,
                                                        ),
                                           "case" => array(
                                                           "schema" => array("int", array("req"=>1)), # normalize manually ATM
                                                           "summary" => "Word case",
                                                           ),
                                           ),
                           "result_naked" => 1,
                           );
function terbilang($args = array()) {
  $x = $args['n'];
  $style = isset($args['style']) ? $args['style'] : 4;

  if($x<0) {
    $hasil = "minus ". trim(kekata($x));
  } else {
    $hasil = trim(kekata($x));
  }
  switch ($style) {
  case 1:
    $hasil = strtoupper($hasil);
    break;
  case 2:
    $hasil = strtolower($hasil);
    break;
  case 3:
    $hasil = ucwords($hasil);
    break;
  default:
    $hasil = ucfirst($hasil);
    break;
  }
  return $hasil;
}

class Phinci_Access_TCP_Server_Handler extends Net_Server_Handler {
  private $bufs     = array();
  private $req_size = array();

  function clearRequest($clientId) {
    if (isset($this->req_size[$clientId])) unset($this->req_size[$clientId]);
    if (isset($this->bufs[$clientId])) unset($this->bufs[$clientId]);
  }

  function sendResponse($clientId, $res) {
    $res_json = json_encode($res);
    $this->_server->sendData($clientId, "J" . strlen($res_json) . "\015\012");
    $this->_server->sendData($clientId, $res_json);
    $this->_server->sendData($clientId, "\015\012");
  }

  function processRiapRequest($clientId, $req) {
    global $SPEC;

    if ($req['uri'] == '/terbilang') {
      if ($req['action'] == 'call') {
        $res = array(200, "OK", terbilang($req['args']));
      } else if ($req['action'] == 'info') {
        $res = array(200, "OK", array("type"=>"function", "uri"=>"php:/terbilang", "v"=>1.1));
      } else if ($req['action'] == 'meta') {
        $res = array(200, "OK", $SPEC['terbilang']);
      } else {
        $res = array(502, "Action not implemented");
      }
    } else {
      $res = array(404, "Not found");
    }
    $this->sendResponse($clientId, $res);
    $this->clearRequest($clientId);
  }

  function onReceiveData($clientId=0, $data="") {
    if (isset($this->req_size[$clientId])) {
      $this->bufs[$clientId] .= $data;
      if (strlen($this->bufs[$clientId]) >= $this->req_size[$clientId]) {
        $req = json_decode($this->bufs[$clientId], true);
        $this->processRiapRequest($clientId, $req);
      }
    } else if (preg_match('/^j(.+)/', $data, $m)) {
      $req = json_decode($m[1], true);
      $this->processRiapRequest($clientId, $req);
    } else if (preg_match('/^J(\d+)/', $data, $m)) {
      $this->req_size[$clientId] = $m[1];
      $this->bufs[$clientId] = "";
   } else {
      $this->sendResponse($clientId, array(400, "Invalid request line"));
      $this->clearRequest($clientId);
    }
  }
}

$server = &Net_Server::create('fork', 'localhost', 9090);
$handler = &new Phinci_Access_TCP_Server_Handler;
$server->setCallbackObject($handler);
$server->start();
