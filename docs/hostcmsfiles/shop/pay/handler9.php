<?php

/**
 * ROBOKASSA
 *
 * Для отправки СМС через сервис Робокассы:
 * 1. Включите опцию отправки СМС в личном кабинете Робокассы.
 * 2. Добавьте в конец bootstrap.php строки:
 <code>
 // Robokassa SMS observers
 Core_Event::attach('shop_order.onAfterChangeStatusPaid', array('Shop_Observer_Robokassa', 'onAfterChangeStatusPaid'));
 Core_Event::attach('Shop_Payment_System_Handler.onAfterProcessOrder', array('Shop_Observer_Robokassa', 'onAfterProcessOrder'));
 </code>
 *
 */
class Shop_Payment_System_Handler9 extends Shop_Payment_System_Handler
{
	// Логин в системе ROBOKASSA
	protected $_mrh_login = "myLogin";

	// Пароль #1 - используется интерфейсом инициализации оплаты
	protected $_mrh_pass1 = "12345";

	// Пароль #2 - регистрационная информация
	protected $_mrh_pass2 = "67890";

	// Язык
	protected $_culture = "ru";

	/* предлагаемая валюта платежа, default payment e-currency.
	 Валюта НЕ связана с $robokassa_currency, это только предлагаемая валюта по умолчанию
	Доступные валюты: https://auth.robokassa.ru/Merchant/WebService/Service.asmx/GetCurrencies?MerchantLogin=demo&Language=ru
	*/
	protected $_in_curr = "BANKOCEAN2R";

	// Код валюты в магазине HostCMS для валюты платежа в личном кабинете Робокассы
	protected $_robokassa_currency = 1;

	// Коэффициент перерасчета при оплате ROBOKASSA
	protected $_coefficient = 1;

	// Режим работы, 0 - Тестовый, 1 - Рабочий
	protected $_mode = 1;

	// Использовать расчёт суммы к получению магазином.
	protected $_calc_out_summ = FALSE;

	/**
	 * Метод, вызываемый в коде настроек ТДС через Shop_Payment_System_Handler::checkBeforeContent($oShop);
	 */
	public function checkPaymentBeforeContent()
	{
		// для отличия от SuccessURL/FailURL
		if (isset($_REQUEST['SignatureValue']) && !isset($_REQUEST['Culture']))
		{
			// Получаем ID заказа
			$order_id = intval(Core_Array::getRequest('InvId'));

			$oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

			if (!is_null($oShop_Order->id))
			{
				// Вызов обработчика платежной системы
				Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
					->shopOrder($oShop_Order)
					->paymentProcessing();
			}

			exit();
		}
	}

	/**
	 * Метод, вызываемый в коде ТДС через Shop_Payment_System_Handler::checkAfterContent($oShop);
	 */
	public function checkPaymentAfterContent()
	{
		if (isset($_REQUEST['SignatureValue']) && isset($_REQUEST['Culture']))
		{
			// Получаем ID заказа
			$order_id = intval(Core_Array::getRequest('InvId'));

			$oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

			if (!is_null($oShop_Order->id))
			{
				// Вызов обработчика платежной системы
				Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
					->shopOrder($oShop_Order)
					->paymentProcessing();
			}
		}
	}

	/*
	 * Метод, запускающий выполнение обработчика
	 */
	public function execute()
	{
		parent::execute();

		$this->printNotification();

		return $this;
	}

	protected function _processOrder()
	{
		parent::_processOrder();

		// Установка XSL-шаблонов в соответствии с настройками в узле структуры
		$this->setXSLs();

		// Отправка писем клиенту и пользователю
		$this->send();

		return $this;
	}

	public function paymentProcessing()
	{
		// Пользователь перешел на страницу с уведомлением о статусе заказа
		if (isset($_REQUEST['InvId']) && isset($_REQUEST['Culture']))
		{
			$this->ShowResultMessage();
			return TRUE;
		}

		// Пришло подтверждение оплаты, обработаем его
		if (isset($_REQUEST['InvId']))
		{
			$this->ProcessResult();
			return TRUE;
		}
	}

	public function getSumWithCoeff()
	{
		return Shop_Controller::instance()->round(($this->_robokassa_currency > 0
				&& $this->_shopOrder->shop_currency_id > 0
			? Shop_Controller::instance()->getCurrencyCoefficientInShopCurrency(
				$this->_shopOrder->Shop_Currency,
				Core_Entity::factory('Shop_Currency', $this->_robokassa_currency)
			)
			: 0) * $this->_shopOrder->getAmount() * $this->_coefficient);
	}

	public function calcOutSumm()
	{
		$outSum = $this->getSumWithCoeff();

		$sUrl = 'https://merchant.roboxchange.com/WebService/Service.asmx/CalcOutSumm?MerchantLogin=' . $this->_mrh_login . '&IncCurrLabel=' . $this->_in_curr . '&IncSum=' . $outSum;

		try
		{
			$Core_Http = Core_Http::instance()
				->url($sUrl)
				->port(80)
				->timeout(5)
				->execute();

			$data = $Core_Http->getBody();

			$oXml = @simplexml_load_string($data);

			$outSum = strval($oXml->OutSum);
		}
		catch (Exception $e){}

		return $outSum;
	}

	public function getNotification()
	{
		$sRoboSum = $this->_calc_out_summ
			? $this->calcOutSumm()
			: $this->getSumWithCoeff();

		ob_start();

		?>
		<h1>Оплата через систему ROBOKASSA</h1>

		<p>
		<a href="http://www.robokassa.ru/" target="_blank">
		<img src="http://www.robokassa.ru/Images/logo.gif" border="0" alt="Система электронных платежей">
		</a>
		</p>
		<p>Сумма к оплате составляет <strong><?php echo $this->_shopOrder->sum()?></strong></p>

		<p>Для оплаты нажмите кнопку "Оплатить".</p>

		<p style="color: rgb(112, 112, 112);">
		Внимание! Нажимая &laquo;Оплатить&raquo; Вы подтверждаете передачу контактных данных на сервер ROBOKASSA для оплаты.
		</p>

		<form action="https://merchant.roboxchange.com/Index.aspx<?php echo $this->_mode == 0 ? '?IsTest=1' : ''?>" method="post">
			<input type="hidden" name="MrchLogin" value="<?php echo $this->_mrh_login?>">
			<input type="hidden" name="OutSum" value="<?php echo $sRoboSum?>">
			<input type="hidden" name="InvId" value="<?php echo $this->_shopOrder->id?>">
			<input type="hidden" name="Desc" value="<?php echo "Оплата счета N {$this->_shopOrder->invoice}"?>">
			<input type="hidden" name="SignatureValue" value="<?php echo md5("{$this->_mrh_login}:{$sRoboSum}:{$this->_shopOrder->id}:{$this->_mrh_pass1}")?>">
			<input type="hidden" name="IncCurrLabel" value="<?php echo $this->_in_curr?>">
			<input type="hidden" name="Culture" value="<?php echo $this->_culture?>">
			<input type="submit" value="Оплатить">
		</form>
		<?php

		return ob_get_clean();
	}

	public function getInvoice()
	{
		return $this->getNotification();
	}

	// Вывод сообщения об успешности/неуспешности оплаты
	function ShowResultMessage()
	{
		$oShop_Order = $this->_shopOrder;

		if (is_null($oShop_Order->id))
		{
			// Заказ не найден
			return FALSE;
		}

		$sRoboHash = Core_Array::getRequest('SignatureValue', '');
		$sRoboSum = Core_Array::getRequest('OutSum', '');

		$sHostcmsSum = sprintf("%.6f", $this->_calc_out_summ ? $this->calcOutSumm() : $this->getSumWithCoeff());

		$sHostcmsHash = $sRoboSum == $sHostcmsSum
			// Для SuccessURL и FailURL используется mrh_pass1!
			? md5("{$sRoboSum}:{$oShop_Order->id}:{$this->_mrh_pass1}")
			: '';

		// Сравниваем хэши
		if (mb_strtoupper($sHostcmsHash) == mb_strtoupper($sRoboHash))
		{
			$sStatus = $oShop_Order->paid == 1 ? "оплачен" : "не оплачен";

			?><h1>Заказ <?php echo $sStatus?></h1>
			<p>Заказ <strong>№ <?php echo $oShop_Order->invoice?></strong> <?php echo $sStatus?>.</p>
			<?php
		}
		else
		{
			?><p>Хэш не совпал!</p><?php
		}
	}

	/*
	 * Обработка статуса оплаты
	*/
	function ProcessResult()
	{
		$oShop_Order = $this->_shopOrder;

		if (is_null($oShop_Order->id) || $oShop_Order->paid)
		{
			// Заказ не найден
			return FALSE;
		}

		$sRoboHash = Core_Array::getRequest('SignatureValue', '');
		$sRoboSum = Core_Array::getRequest('OutSum', '');

		$sHostcmsSum = sprintf("%.6f", $this->_calc_out_summ ? $this->calcOutSumm() : $this->getSumWithCoeff());

		if ($sRoboSum == $sHostcmsSum)
		{
			// Для SuccessURL и FailURL используется mrh_pass1!
			$sHostcmsHash = md5("{$sRoboSum}:{$oShop_Order->id}:{$this->_mrh_pass2}");

			// Сравниваем хэши
			if (mb_strtoupper($sHostcmsHash) == mb_strtoupper($sRoboHash))
			{
				$this->shopOrder($oShop_Order)->shopOrderBeforeAction(clone $oShop_Order);

				$oShop_Order->system_information = "Товар оплачен через ROBOKASSA.\n";
				$oShop_Order->paid();
				$this->setXSLs();
				$this->send();
				echo "OK{$oShop_Order->id}\n";

				ob_start();
				$this->changedOrder('changeStatusPaid');
				ob_get_clean();
			}
			else
			{
				$oShop_Order->system_information = 'ROBOKASSA хэш не совпал!';
				$oShop_Order->save();
				echo "bad sign\n";
			}
		}
		else
		{
			$oShop_Order->system_information = 'ROBOKASSA сумма не совпала!';
			$oShop_Order->save();
			echo "bad sign\n";
		}
	}
}
