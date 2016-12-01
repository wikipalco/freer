<?
/*
  Virtual Freer
  http://freer.ir/virtual

  Copyright (c) 2011 Mohammad Hossein Beyram, freer.ir

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v3 (http://www.gnu.org/licenses/gpl-3.0.html)
  as published by the Free Software Foundation.
*/
	//-- اطلاعات کلی پلاگین
	$pluginData[wikipal][type] = 'payment';
	$pluginData[wikipal][name] = 'ویکی پال';
	$pluginData[wikipal][uniq] = 'wikipal';
	$pluginData[wikipal][description] = 'درگاه پرداخت <a href="http://wikipal.co">ویکی پال</a>';
	$pluginData[wikipal][author][name] = 'میلاد مالدار';
	$pluginData[wikipal][author][url] = 'https://ltiny.ir';
	$pluginData[wikipal][author][email] = 'info@ltiny.ir';
	
	//-- فیلدهای تنظیمات پلاگین
	$pluginData[wikipal][field][config][1][title] = 'مرچنت کد';
	$pluginData[wikipal][field][config][1][name] = 'merchant';
	$pluginData[wikipal][field][config][2][title] = 'عنوان خرید';
	$pluginData[wikipal][field][config][2][name] = 'title';
	
	//-- تابع انتقال به دروازه پرداخت
	function gateway__wikipal($data)
	{
		global $config,$db,$smarty;

		$MerchantID 			= trim($data[merchant]);
		$amount 				= round($data[amount]/10);
		$invoice_id				= $data[invoice_id];

		$Price 					= round($data[amount]/10);
		$Description 			= $data[title].' - '.$data[invoice_id];
		$InvoiceNumber 			= $invoice_id;
		$CallbackURL 			= $data[callback];
			
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentRequest.php');
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Description=$Description&InvoiceNumber=$InvoiceNumber&CallbackURL=". urlencode($CallbackURL));
		curl_setopt($curl, CURLOPT_TIMEOUT, 400);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = json_decode(curl_exec($curl));
		curl_close($curl);

		if ($result->Status == 100){
			$update[payment_rand]		= $result->Authority;
			$sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "'.$invoice_id.'" LIMIT 1;');
			$db->execute($sql);
			header('location: http://gatepay.co/webservice/startPayment.php?au='. $result->Authority);
			exit;
		} else {
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در اتصال به درگاه مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font> کد خطا : '. $result->Status .'<br /><a href="index.php" class="button">بازگشت</a>';
			$query	= 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
			$conf	= $db->fetch($query);
			$smarty->assign('config', $conf);
			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
		}
	}
	
	//-- تابع بررسی وضعیت پرداخت
	function callback__wikipal($data)
	{
		global $db,$get;
		
		if ($_POST['status'] == 1) {
			
			$Authority 				= $_POST['authority'];
			$InvoiceNumber 			= $_POST['InvoiceNumber'];
			
			$sql 					= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$Authority.'" LIMIT 1;';
			$payment 				= $db->fetch($sql);

			$MerchantID 			= $data[merchant];
			$Price 					= round($payment[payment_amount]/10);

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentVerify.php');
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
			curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Authority=$Authority");
			curl_setopt($curl, CURLOPT_TIMEOUT, 400);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$result = json_decode(curl_exec($curl));
			curl_close($curl);

			if ($result->Status == 100) {
				//-- آماده کردن خروجی
				$output[status]		= 1;
				$output[res_num]	= $Authority;
				$output[ref_num]	= $result->RefCode;
				$output[payment_id]	= $payment[payment_id];
			} else {
				echo $result->Status;
				//-- در تایید پرداخت مشکلی به‌وجود آمده است‌
				$output[status]	= 0;
				$output[message]= 'پرداخت توسط آسمان پرداخت تایید نشد‌, کد خطا : '. $result->Status;
			}

		} else {
			//-- شماره یکتا اشتباه است
			$output[status]	= 0;
			$output[message]= 'عملیات پرداخت لغو شده است';
		}
		return $output;
	}