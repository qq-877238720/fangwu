<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'config_init' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_send' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_notice' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_check' => 
    array (
      0 => 'qcloudsms',
    ),
  ),
  'route' => 
  array (
    '/qrcode$' => 'qrcode/index/index',
    '/qrcode/build$' => 'qrcode/index/build',
  ),
);