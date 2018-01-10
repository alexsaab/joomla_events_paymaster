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
        $this->setParam('LMI_MERCHANT_ID', $params->get('paymaster_merchant_id'));
        $this->setParam('paymaster_secret', $params->get('paymaster_secret'));
        $currency = $params->get('paymaster_currency', 'RUB');
        if ($currency == 'RUR') {
            $currency = 'RUB';
        }
        $this->setParam('LMI_CURRENCY', $currency);
        $this->setParam('paymaster_hash_alg', $params->get('paymaster_hash_alg', 'md5'));
        $this->setParam('paymaster_vat_rate', $params->get('paymaster_vat_rate', 'no_vat'));

        // logging
        $this->pm_log      = $params->get('pm_log', 0);
        $this->pm_log_file = JPATH_COMPONENT . '/pm_logs.txt';
    }

    /**
     * Set param value
     *
     * @param string $name
     * @param string $val
     */
    public function setParam($name, $val)
    {
        $this->_params[$name] = $val;
    }

    /**
     * Setup payment parameter
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
     * Set Post param value
     *
     * @param string $name
     * @param string $val
     */
    public function setPostParam($name, $val)
    {
        $this->_post_params[$name] = $val;
    }

    /**
     * Setup Post payment parameter
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
        $this->setPostParam('MNT_AMOUNT', $amount);
        $this->setPostParam('MNT_CURRENCY_CODE', $this->_params['currency_code']);
        $this->setPostParam('MNT_DESCRIPTION', $description);
        $this->setPostParam('MNT_TRANSACTION_ID', $transactionId);

        $test_mode = $this->_params['paymaster_test'];
        if (!$test_mode) {
            $test_mode = '0';
        }

        $signature = md5($this->_params['account_id'] . $transactionId . $amount . $this->_params['currency_code'] . $test_mode . $this->_params['account_code']);
        $this->setPostParam('MNT_SIGNATURE', $signature);

        $this->setPostParam('MNT_SUCCESS_URL', $siteUrl . 'index.php?option=com_eventbooking&view=complete&Itemid=' . $Itemid);
        $this->setPostParam('MNT_FAIL_URL', $siteUrl . 'index.php?option=com_eventbooking&task=cancel&id=' . $row->id . '&Itemid=' . $Itemid);

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

    public function makeHash($data = array(), $secret = '', $hash_alg = 'md5') {

    }

    /**
     * Возвращаем подпись продавца SIGN
     * @param array $data
     * @param string $secret
     * @param string $hash_alg
     * @return string
     */
    public function makeSign($data = array(), $secret = '', $hash_alg = 'md5') {
        $plain_sign = $data['merchant_id'] . $data['order_id'] . $data['amount'] . $data['lmi_currency'] . $secret;
        return base64_encode(hash($hash_alg, $plain_sign, true));
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

    /**
     * Process payment
     *
     */
    public function verifyPayment()
    {

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
        $MNT_ID             = $this->getRequestVar('MNT_ID');
        $MNT_TRANSACTION_ID = $this->getRequestVar('MNT_TRANSACTION_ID');
        $MNT_OPERATION_ID   = $this->getRequestVar('MNT_OPERATION_ID');
        $MNT_AMOUNT         = $this->getRequestVar('MNT_AMOUNT');
        $MNT_CURRENCY_CODE  = $this->getRequestVar('MNT_CURRENCY_CODE');
        $MNT_SUBSCRIBER_ID  = $this->getRequestVar('MNT_SUBSCRIBER_ID');
        $MNT_TEST_MODE      = $this->getRequestVar('MNT_TEST_MODE');
        if (!$MNT_TEST_MODE) {
            $MNT_TEST_MODE = '0';
        }

        $MNT_SIGNATURE = $this->getRequestVar('MNT_SIGNATURE');
        // check signature
        $signature = md5($MNT_ID . $MNT_TRANSACTION_ID . $MNT_OPERATION_ID . $MNT_AMOUNT . $MNT_CURRENCY_CODE . $MNT_SUBSCRIBER_ID . $MNT_TEST_MODE . $this->_params['account_code']);
        if ($MNT_SIGNATURE != $signature) {
            echo "FAIL";exit;
        }

        $id = $MNT_TRANSACTION_ID;
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
}