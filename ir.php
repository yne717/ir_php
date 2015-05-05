<?php

function generateIRCode($rakaoke_number) {
    $codes = array(
        array(0xD1, 0x2D, 0x08, 0xF7),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x3C, 0xC3),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x00, 0x00),
        array(0xD1, 0x2D, 0x09, 0xF6),
    );
    $code_types = array(
        'reader' => array(0x01, 0x56, 0x00, 0xAB),
        'off'    => array(0x00, 0x15, 0x00, 0x15),
        'on'     => array(0x00, 0x15, 0x00, 0x40),
        'end'    => array(0x00, 0x15, 0x08, 0xFD),
    );

    $codes[1][2] = (($rakaoke_number % 1000000) / 100000) + 0x30;
    $codes[1][3] = ~$codes[1][2] & 0xFF;
    $codes[2][2] = (($rakaoke_number % 100000) / 10000) + 0x30;
    $codes[2][3] = ~$codes[2][2] & 0xFF;
    $codes[3][2] = (($rakaoke_number % 10000) / 1000) + 0x30;
    $codes[3][3] = ~$codes[3][2] & 0xFF;
    $codes[4][2] = (($rakaoke_number % 1000) / 100) + 0x30;
    $codes[4][3] = ~$codes[4][2] & 0xFF;
    $codes[6][2] = (($rakaoke_number % 100) / 10) + 0x30;
    $codes[6][3] = ~$codes[6][2] & 0xFF;
    $codes[7][2] = ($rakaoke_number % 10) + 0x30;
    $codes[7][3] = ~$codes[7][2] & 0xFF;

    $generate_codes = array();
    for ($i = 0; $i < count($codes); $i++) {
        foreach ($code_types['reader'] as $code) {
            $generate_codes[] = $code;
        }
        for ($j = 0; $j < count($codes[$i]); $j++) {
            for ($k = 0; $k < 8; $k++) {
                $type = ((($codes[$i][$j] >> $k) & 0x01) == 1) ? 'on' : 'off';
                foreach ($code_types[$type] as $code) {
                    $generate_codes[] = $code;
                }
            }
        }
        foreach ($code_types['end'] as $code) {
            $generate_codes[] = $code;
        }
    }
    return $generate_codes;
}



function transfer_ir ($code, $handl, $ep) {
  $buf = array();
  $IR_SEND_DATA_USB_SEND_MAX_LEN = 14;
  $send_bit_num = 0;
  $send_bit_pos = 0;
  $set_bit_size = 0;
  $send_bit_num = 1224/4;
  $length = $IR_SEND_DATA_USB_SEND_MAX_LEN;
  $size = 0;
  $timeout = 1000;


  for ($i=0; $i<65; $i++) {
    $buf[$i] = 0xFF;
  }

  while (true) {
    $buf[0] = '';
    $buf[1] = 0x34;
    $buf[2] = ($send_bit_num >> 8) & 0xFF;
    $buf[3] = $send_bit_num & 0xFF;
    $buf[4] = ($send_bit_pos >> 8) & 0xFF;
    $buf[5] = $send_bit_pos & 0xFF;

    if ($send_bit_num > $send_bit_pos) {
      $set_bit_size = $send_bit_num - $send_bit_pos;
      if ($set_bit_size > $IR_SEND_DATA_USB_SEND_MAX_LEN) {
        $set_bit_size = $IR_SEND_DATA_USB_SEND_MAX_LEN;
      }
    } else {
      $set_bit_size = 0;
    }

    $buf[6] = $set_bit_size & 0xFF;

    if ($set_bit_size > 0) {

      for ($i=0; $i<$set_bit_size; $i++) {
        $buf[7 + ($i * 4)] = $code[$send_bit_pos * 4];
        $buf[7 + ($i * 4) + 1] = $code[($send_bit_pos * 4) + 1];
        $buf[7 + ($i * 4) + 2] = $code[($send_bit_pos * 4) + 2];
        $buf[7 + ($i * 4) + 3] = $code[($send_bit_pos * 4) + 3];
        $send_bit_pos++;
      }

     $outbuf = "";
     for ($i=0; $i<count($buf); $i++) {
       $outbuf = $outbuf . dechex($buf[$i]);
     }

      $r = usb_interrupt_transfer($handle, $ep, $outbuf, $length, $size, $timeout);
      echo $outbuf . PHP_EOL;
      echo usb_error_name($r) . PHP_EOL;

      
    } else {
      break;
    }

  }


  $IR_FREQ = 38000;

  $buf[0] = '';
  $buf[1] = 0x35;
  $buf[2] = ($IR_FREQ >> 8) & 0xFF;
  $buf[3] = $IR_FREQ & 0xFF;
  $buf[4] = ($send_bit_num >> 8) & 0xFF;
  $buf[5] = $send_bit_num & 0xFF;

  for ($i=0; $i<count($buf); $i++) {
    $outbuf = $outbuf . dechex($buf[$i]);
  }

  $r = usb_interrupt_transfer($handle, $ep, $outbuf, $length, $size, $timeout);
  echo $outbuf . PHP_EOL;
  echo usb_error_name($r) . PHP_EOL;
  

}
  

//$code = generateIRCode(111111);

$context = null;
$vid = 0x22ea;
$pid = 0x0039;
$ep_out = 0x01;
$ep_in = 0x81;
$devh = null;

$r = usb_init($context);
if ($r != USB_SUCCESS) {
    die ('failed to usb_init(). ' . usb_error_name($r));
    exit();
}

$devh = usb_open_device_with_vid_pid($context, $vid, $pid);
if ($devh == null) {
  printf('failed to open(vid: 0x%04x, pid: 0x%04x)' . PHP_EOL, $vid, $pid);
  exit();
}

$r = usb_kernel_driver_active($devh, 0);
if ($r == 1) {
  $r = usb_detach_kernel_driver($devh, 0);
  if ($r != 0) {
    print 'detaching kernel driver failed' . PHP_EOL;
    exit();
	}
}

$r = usb_claim_interface($devh, 0);
if ($r < 0) {
  print 'claim interface failed' . PHP_EOL;
  exit();
}


$code = generateIRCode(111111);
transfer_ir($code, $devh, $ep);


if ($devh) usb_close($devh);
if ($context) usb_exit($context);



//transfer($code, $devh, $ep);
//$ep = 0x01;
//$ep_in = 0x81;
//$buf = '0x35';
//$size = 0;
//$size_ = 0;
//$r = usb_interrupt_transfer($device_handle, $ep, $buf, $size, $size_, 1000);
//echo usb_error_name($r) . PHP_EOL;
//var_dump($buf);

//$fetch = null;
//$r = usb_control_transfer(
//    $device_handle,
//    USB_ENDPOINT_IN,
//    USB_REQUEST_GET_STATUS,
//    0, 0, $fetch, -1, 10000);
//echo usb_error_name($r) . PHP_EOL;
//var_dump($fetch);


//closeDevice($device_handle);

//$vid = 8938;
//$pid = 57;
//
//$device_resources = getDeviceList();
//
//$handle;
//$device_descriptor;
//
//for ($i=0; $i<count($device_resources); $i++) {
//  usb_get_device_descriptor($device_resources[$i], $device_descriptor);
//  if ($device_descriptor->idVendor == $vid && $device_descriptor->idProduct == $pid) {
//    $result_open = usb_open($device_resources[$i], $handle);
//var_dump($handle);
//    if ($result_open != USB_SUCCESS) {
//      print 'faild open ' . usb_error_name($result_open) . PHP_EOL;
//
//    }
//    break;
//  }
//}
//
//var_dump($device);



