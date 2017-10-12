<?php

require_once __DIR__ . '/vendor/autoload.php';

$options = getopt('', ['key:']);

$oGandi = new \BespokeSupport\Gandi\GandiAPILive($options['key']);

$additionalIps = 5;

// Trouver le datacenter FR
$datacenters = $oGandi->hostingDatacenter->list();
$datacenterFr = array_values(array_filter($datacenters, function($datacenter){return $datacenter['iso'] == 'FR';}))[0];

// TRouver debian 8
$images = $oGandi->hostingImage->list(array('datacenter_id' => $datacenterFr['id']));
$imageDebian = array_values(array_filter($images, function($image){return $image['system'] == 'Debian' && $image['version'] == '8';}))[0];

// On prend les keys ssh
$keys = $oGandi->hostingSsh->list();

var_dump($datacenterFr, $imageDebian, $keys);

// On créé la VM
$vmInfo = $oGandi->hostingVm->create_from(array(
    'datacenter_id' => $datacenterFr['id'],
    'password' => uniqid(),
    'keys' => array_column($keys, 'id')
), array(
    'datacenter_id' => $datacenterFr['id']
), $imageDebian['disk_id']);

$vmId = array_values(array_filter(array_column($vmInfo, 'vm_id')))[0];
$ifaceId = array_values(array_filter(array_column($vmInfo, 'iface_id')))[0];

// On ajoute des ifaces
$ifaces = array();
for($i = 0; $i < $additionalIps; $i ++){
    try{
        $iface = $oGandi->hostingIface->create(array(
            'datacenter_id' => $datacenterFr['id'],
            'ip_version' => '4'
        ));
        $ifaces[] = $iface;
        $oGandi->hostingVm->iface_attach($vmId, $iface['iface_id']);
    }catch(\Exception $e){
        if(strpos($e->getMessage(), 'CAUSE_QUOTA_REACHED') !== false){
            throw $e;
        }
    }
}
