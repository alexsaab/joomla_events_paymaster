<?php
/**
 * @version            1.0.0
 * @package            Joomla
 * @subpackage        Event Booking
 * @author          Alex Agafonov
 * @copyright        Copyright (C) 2017 PayMaster
 * @license            GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die();

class os_paymaster extends os_payment
{

    /**
     * paymaster mode
     *
     * @var boolean live mode : true, test mode : false
     */
    public $_mode = 0;

    /**
     * paymaster url
     *
     * @var string
     */
//    public $_url = null;
    // Предварительно так
    public $_url = "https://paymaster.ru/Payment/Init";

    /**
     * Array of params will be posted to server
     *
     * @var string
     */
    public $_params = array();

    /**
     * Array of post params
     *
     * @var array
     */
    public $_post_params = array();

    /**
     * Array containing data posted from paymaster to our server
     *
     * @var array
     */
    public $_data = array();

    /**
     * Constructor functions, init some parameter
     *
     * @param object $config
     */
    public function os_paymaster($params)
    {
        parent::setName('os_paymaster');
        parent::os_payment();
        parent::setCreditCard(false);
        parent::setCardType(false);
        parent::setCardCvv(false);
        parent::setCardHolderName(false);

        $this->_mode = $params->get('paymaster_mode');
        if ($this->_mode) {
            $this->setParam('LMI_SIM_MODE', 1); 
        }         

        // paymaster specific params
        $this->setParam('paymaster_merchant_id', $params->get('paymaster_merchant_id'));
        $this->setParam('paymaster_secret', $params->get('paymaster_secret'));
        $currency = $params->get('paymaster_currency', 'RUB');
        if ($currency == 'RUR') {
            $currency = 'RUB';
        }
        $this->setParam('paymaster_currency', $currency);
        $this->setParam('paymaster_hash_alg', $params->get('paymaster_hash_alg', 'md5'));
        $this->setParam('paymaster_vat_rate', $params->get('paymaster_vat_rate', 'no_vat'));

        // logging
        $this->pm_log      = $params->get('pm_log', 0);
        $this->pm_log_file = JPATH_COMPONENT . '/pm_logs.txt';
    }

    /**
     * Process Payment
     *
     * @param object $row
     * @param array $params
     */
    public function processPayment($row, $data)
    {
        $Itemid  = JRequest::getInt('Itemid', 0);
        $siteUrl = JUri::base();

        $itemName = JText::_('EB_EVENT_REGISTRATION');
        $itemName = str_replace('[EVENT_TITLE]', $data['event_title'], $itemName);

        $description = "Address: " . EventbookingHelper::getCountryCode($row->country) . "{$row->city} {$row->state} {$row->zip} {$row->address} {$row->address2}, Client: {$row->first_name} {$row->last_name} {$row->email}, Order: {$itemName} | {$Itemid} | {$row->id} | {$row->event_id}";
        $amount      = number_format($data['amount'], 2, '.', '');

        $transactionId = $Itemid . '_' . $row->event_id . '_' . $row->id;

        $this->setPostParam('LMI_MERCHANT_ID', $this->_params['paymaster_merchant_id']);
        $this->setPostParam('LMI_PAYMENT_AMOUNT', $amount);
        $this->setPostParam('LMI_CURRENCY', $this->_params['paymaster_currency']);
        $this->setPostParam('LMI_PAYMENT_DESC', $description);
        $this->setPostParam('LMI_PAYMENT_NO', $transactionId);

        // Формируем подпись
        $dataSet = array(
            'LMI_MERCHANT_ID' => $this->_params['paymaster_merchant_id'],
            'LMI_PAYMENT_NO' => $transactionId,
            'LMI_PAYMENT_AMOUNT' => $amount,
            'LMI_CURRENCY' => $this->_params['paymaster_currency'],
        );

        $sign = $this->makeSign($dataSet, $this->_params['paymaster_secret'], $this->_params['paymaster_hash_alg']);

        // Для подключения к онлайн кассе делаем
        // Так как товар один - регистрация на меропрятие ограничиваемся только первым [0] значением массива

        $this->setPostParam('LMI_SHOPPINGCART.ITEMS[0].NAME', $description);
        $this->setPostParam('LMI_SHOPPINGCART.ITEMS[0].QTY', 1);
        $this->setPostParam('LMI_SHOPPINGCART.ITEMS[0].PRICE', $amount);
        $this->setPostParam('LMI_SHOPPINGCART.ITEMS[0].TAX', $this->_params['paymaster_vat_rate']);

        $this->setPostParam('SIGN', $sign);

        $test_mode = $this->_params['paymaster_test'];
        if (!$test_mode) {
            $test_mode = '0';
        }


        $this->setPostParam('LMI_PAYMENT_NOTIFICATION_URL', $siteUrl . 'index.php?option=com_eventbooking&task=payment_confirm&payment_method=os_paymaster&Itemid=' . $Itemid);

        //TODO
        //Возможно будет правильнее
        // $this->setPostParam('LMI_PAYMENT_NOTIFICATION_URL', $siteUrl . 'index.php?option=com_eventbooking&task=payment_confirm&payment_method=os_paymaster');

        $this->setPostParam('LMI_SUCCESS_URL', $siteUrl . 'index.php?option=com_eventbooking&view=complete&Itemid=' . $Itemid);
        $this->setPostParam('LMI_FAILURE_URL', $siteUrl . 'index.php?option=com_eventbooking&task=cancel&id=' . $row->id . '&Itemid=' . $Itemid);

        // Pay URL:
        // $siteUrl . 'index.php?option=com_eventbooking&task=payment_confirm&payment_method=os_paymaster'

        $this->submitPost();
    }


    /**
     * Submit post to paypal server
     *
     */
    public function submitPost()
    {
        ?>
		<div class="contentheading">Process payment ... Please wait ...</div>
		<form method="post" action="<?php echo $this->_url; ?>" name="jd_form" id="jd_form">
			<?php
foreach ($this->_post_params as $key => $val) {
            echo '<input type="hidden" name="' . $key . '" value="' . $val . '" />';
            echo "\n";
        }

        ?>
			<script type="text/javascript">
				function redirect()
				{
					document.jd_form.submit();
				}
				setTimeout('redirect()', 3000);
			</script>
		</form>
	<?php
}

    /**
     * Log result
     *
     * @param string $success
     */
    public function log_pm_results($success)
    {
        if (!$this->pm_log) {
            return;
        }
        $text = '[' . date('m/d/Y g:i A') . '] - ';
        if ($success) {
            $text .= "SUCCESS!\n";
        } else {
            $text .= 'FAIL: ' . $this->last_error . "\n";
        }
        $text .= "POST Vars from PayMaster:\n";
        foreach ($this->_data as $key => $value) {
            $text .= "$key=$value, ";
        }
        $text .= "\nResponse from PayMaster Server:\n " . $this->pm_response;
        $fp = fopen($this->pm_log_file, 'a');
        fwrite($fp, $text . "\n\n");
        fclose($fp); // close file
    }





    /**
     * Process payment
     *
     */
    public function verifyPayment()
    {

        // В принципе это можно убрать, так как это обработчик ошибок
        $errorHandlerFileName = date("Ymd") . ".txt";
        $errorHandlerMessage  = "POST: " . print_r($_POST, true) . " GET: " . print_r($_GET, true);

        $fp = fopen($errorHandlerFileName, "a");
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $errorHandlerMessage);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        $config = EventbookingHelper::getConfig();

        // Request params
        $LMI_MERCHANT_ID = $this->getRequestVar('LMI_MERCHANT_ID');
        $LMI_PAYMENT_NO = $this->getRequestVar('LMI_PAYMENT_NO');
        $LMI_PAYMENT_AMOUNT = $this->getRequestVar('LMI_PAYMENT_AMOUNT');
        $LMI_SYS_PAYMENT_ID = $this->getRequestVar('LMI_SYS_PAYMENT_ID');
        $LMI_SYS_PAYMENT_DATE = $this->getRequestVar('LMI_SYS_PAYMENT_DATE');
        $LMI_CURRENCY = $this->getRequestVar('LMI_CURRENCY');
        $LMI_PAID_AMOUNT = $this->getRequestVar('LMI_PAID_AMOUNT');
        $LMI_PAID_CURRENCY = $this->getRequestVar('LMI_PAID_CURRENCY');
        $LMI_PAYMENT_METHOD = $this->getRequestVar('LMI_PAYMENT_METHOD');
        $LMI_SIM_MODE = $this->getRequestVar('LMI_SIM_MODE');
        $LMI_PAYMENT_DESC = $this->getRequestVar('LMI_PAYMENT_DESC');
        $LMI_HASH = $this->getRequestVar('LMI_HASH');
        $LMI_PAYER_COUNTRY = $this->getRequestVar('LMI_PAYER_COUNTRY');
        $LMI_PAYER_PASSPORT_COUNTRY = $this->getRequestVar('LMI_PAYER_PASSPORT_COUNTRY');
        $LMI_PAYER_IP_ADDRESS = $this->getRequestVar('LMI_PAYER_IP_ADDRESS');
        $SIGN = $this->getRequestVar('SIGN');



        // Думаю, что это пока сейчас не нужно.
//        if (!$MNT_TEST_MODE) {
//            $MNT_TEST_MODE = '0';
//        }



        // check signatures

        $dataSet = array(
                'LMI_MERCHANT_ID' => $LMI_MERCHANT_ID,
                'LMI_PAYMENT_NO' => $LMI_PAYMENT_NO,
                'LMI_SYS_PAYMENT_ID' => $LMI_SYS_PAYMENT_ID,
                'LMI_SYS_PAYMENT_DATE' => $LMI_SYS_PAYMENT_DATE,
                'LMI_PAYMENT_AMOUNT' => $LMI_PAYMENT_AMOUNT,
                'LMI_CURRENCY' => $LMI_CURRENCY,
                'LMI_PAID_AMOUNT' => $LMI_PAID_AMOUNT,
                'LMI_PAID_CURRENCY' => $LMI_PAID_CURRENCY,
                'LMI_PAYMENT_METHOD' => $LMI_PAYMENT_METHOD,
                'LMI_SIM_MODE' => $LMI_SIM_MODE
        );

        $hash = $this->makeHash($dataSet, $this->_params['paymaster_secret'], $this->_params['paymaster_hash_alg']);
        $sign = $this->makeSign($dataSet, $this->_params['paymaster_secret'], $this->_params['paymaster_hash_alg']);

        if (($sign != $SIGN) || ($hash != $LMI_HASH)) {
            echo "FAIL";exit;
        }

        $id = $LMI_PAYMENT_NO;

        if (strpos($id, '_') !== false) {
            $transactionIdArray = explode('_', $id);
            if (isset($transactionIdArray[2])) {
                $id = $transactionIdArray[2];
            }
        }

        $row = JTable::getInstance('EventBooking', 'Registrant');
        $row->load($id);
        if (!$row->id) {
            echo "FAIL";exit;
        }
        if ($row->published) {
            echo "FAIL";exit;
        }

        // save payment transaction
        $row->payment_date = gmdate('Y-m-d H:i:s');
        $row->published    = true;
        $row->checked_in   = 1;
        $row->store();
        if ($row->is_group_billing) {
            EventbookingHelper::updateGroupRegistrationRecord($row->id);
        }
        EventbookingHelper::sendEmails($row, $config);
        JPluginHelper::importPlugin('eventbooking');
        $dispatcher = JDispatcher::getInstance();
        $dispatcher->trigger('onAfterPaymentSuccess', array($row));

        echo "SUCCESS";exit;
    }


    /**
     * Базовый алгоритм формирования подписи (с проверкой)
     * @param array $data
     * @param string $secret
     * @param string $hash_method
     * @return string
     */
    public function makeHash($data = array(), $secret = '', $hash_method = 'sha256') {
        $string = $data['LMI_MERCHANT_ID'] . ";" . $data['LMI_PAYMENT_NO'] . ";" . $data['LMI_SYS_PAYMENT_ID'] . ";" . $data['LMI_SYS_PAYMENT_DATE'] . ";" . $data['LMI_PAYMENT_AMOUNT'] . ";" . $data['LMI_CURRENCY'] . ";" . $data['LMI_PAID_AMOUNT'] . ";" . $data['LMI_PAID_CURRENCY'] . ";" . $data['LMI_PAYMENT_SYSTEM'] . ";" . $data['LMI_SIM_MODE'] . ";" . $secret;
        return base64_encode(hash($hash_method, $string, true));
    }

    /**
     * Возвращаем подпись продавца SIGN
     * @param array $data
     * @param string $secret
     * @param string $hash_method
     * @return string
     */
    public function makeSign($data = array(), $secret = '', $hash_method = 'sha256') {
        $plain_sign = $data['LMI_MERCHANT_ID'] . $data['LMI_PAYMENT_NO'] . $data['LMI_PAYMENT_AMOUNT'] . $data['LMI_CURRENCY'] . $secret;
        return base64_encode(hash($hash_method, $plain_sign, true));
    }

    /**
     * Сеттер одного параметра
     *
     * @param string $name
     * @param string $val
     */
    public function setParam($name, $val)
    {
        $this->_params[$name] = $val;
    }

    /**
     * Сеттер параметров (когда их много)
     *
     * @param array $params
     */
    public function setParams($params)
    {
        foreach ($params as $key => $value) {
            $this->_params[$key] = $value;
        }
    }

    /**
     * Сеттер пост-параметра (один)
     *
     * @param string $name
     * @param string $val
     */
    public function setPostParam($name, $val)
    {
        $this->_post_params[$name] = $val;
    }

    /**
     * Сеттер пост-параметров (много)
     *
     * @param array $params
     */
    public function setPostParams($params)
    {
        foreach ($params as $key => $value) {
            $this->_post_params[$key] = $value;
        }
    }

    /**
     * Функция возвращает значения параметра либо из POST либо из GET запроса
     * @param  [type] $name [description]
     * @return [type]       [description]
     */
    public function getRequestVar($name)
    {
        $value = null;
        if (isset($_POST[$name])) {
            $value = $_POST[$name];
        } else if (isset($_GET[$name])) {
            $value = $_GET[$name];
        }
        return $value;
    }
}