<?php
class Fooman_Jirafe_Model_Report extends Mage_Core_Model_Abstract
{

    const XML_PATH_EMAIL_TEMPLATE   = 'fooman/jirafe/report_email_template';
    const XML_PATH_EMAIL_IDENTITY   = 'fooman/jirafe/report_email_identity';

    protected $_helper = '';


    protected function _construct ()
    {
        $this->_init('foomanjirafe/report');
        $this->_helper = Mage::helper('foomanjirafe');
    }

    /*
    * website_id - The Jirafe website ID for this particular Magento instance.
    * email - Array of information about the people to email the daily reports to. email + first_name + last_name
    * stores - Array of information about the stores that have been set up in this Magento instance. store_id + description + base_url
    * time_zone - The time zone that this store is set up in
    * currency - The currency that this store uses
     *
    * dt - The date that this data comes from
    * num_orders - Number of orders made in the previous day
    * revenue - Amount of sales made in the previous day
    * num_visitors - Amount of visitors in the previous day
    * num_abandoned_carts - Number of visitors who abandoned carts in the previous day
    * revenue_abandoned_carts - Revenue left in abandoned carts in the previous day

     */

    public function cron ()
    {
        $this->_helper->debug('starting jirafe report cron');
        $websiteId = $this->checkWebsiteId();
        if($websiteId) {
            //we have a valid website id
            //global data
            $currentGmtTimestamp = Mage::getSingleton('core/date')->gmtTimestamp();
            $data = array(
                'website_id' => $websiteId,
                'email' => $this->_helper->getStoreConfig('emails'),
                'currency'=> Mage::getStoreConfig('currency/options/base')
            );
            
            //loop over stores to create reports            
            $storeCollection = Mage::getModel('core/store')->getCollection();
            foreach ($storeCollection as $store) {
                if ($this->_helper->getStoreConfig('isActive', $store->getId())) {
                    $storeData = array();
                    $combinedData = $data;
                    $storeData[$store->getId()] = $this->_gatherReportData($store, $currentGmtTimestamp, $data['currency']);

                    //new report created
                    if ($storeData[$store->getId()]){
                        //combine global and store wide data
                        $combinedData['stores'] = $storeData;

                        //save report for transmission
                        $jirafeVersion = Mage::getResourceModel('core/resource')->getDbVersion('foomanjirafe_setup');
                        $this->_helper->debug($combinedData);
                        Mage::getModel('foomanjirafe/report')
                            ->setStoreId($store->getId())
                            ->setGeneratedByJirafeVersion($jirafeVersion)
                            ->setStoreReportDate($storeData[$store->getId()]['dt'])
                            ->setReportData(json_encode($combinedData))
                            ->save();
                        //send email
                        $this->sendJirafeEmail($storeData[$store->getId()], $store->getId());
                        //notify Jirafe
                        $this->sendJirafeHeartbeat($storeData[$store->getId()], $store->getId());
                    }
                }
            }
        }
        
        $this->_helper->debug('finished jirafe report cron');
    }

    public function checkWebsiteId ()
    {
        $websiteId = $this->_helper->getStoreConfig('websiteId');
        if ($websiteId) {
            return $websiteId;
        } else {
            $email = $this->_helper->getStoreConfig('emails');
            if($email) {
                return $this->_requestWebsiteId($email);
            }
        }
        //we don't have a valid website id
        return false;
    }

    public function sendJirafeEmail($storeData, $storeId)
    {
        $emails = explode(",", $this->_helper->getStoreConfig('emails', $storeId));
        $translate = Mage::getSingleton('core/translate');
        /* @var $translate Mage_Core_Model_Translate */
        $translate->setTranslateInline(false);

        $emailTemplate = Mage::getModel('core/email_template');
        /* @var $emailTemplate Mage_Core_Model_Email_Template */
        foreach ($emails as $email){
            $emailTemplate->setDesignConfig(array('area' => 'backend'))
                ->sendTransactional(
                    Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE),
                    Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY),
                    trim($email),
                    null,
                    $storeData,
                    $storeId

                );
        }
        $translate->setTranslateInline(true);
    }

    public function sendJirafeHeartbeat($storeData, $storeId)
    {
        $data = $storeData;
        $data['admin_emails'] = $this->_helper->getStoreConfig('emails', $storeId);        
        return Mage::getModel('foomanjirafe/api')->sendHeartbeat($data);
    }


    public function _requestWebsiteId ($email)
    {
        //functionality to retrieve new website_id
        $id = md5($email);
        $this->_helper->debug("New Jirafe website_id ". $id);
        $this->_helper->setStoreConfig('websiteId', $id);
        //return Mage::getModel('foomanjirafe/api')->createAccount(array('email'=>$email));
        return $this->_helper->getStoreConfig('websiteId');
    }

    private function _gatherReportData($store, $currentGmtTimestamp, $currency)
    {

        Mage::app()->setCurrentStore($store);
        $currentStoreTimestamp = Mage::getSingleton('core/date')->timestamp($currentGmtTimestamp);
        $offset = $currentStoreTimestamp - $currentGmtTimestamp;
        $format = 'Y-m-d H:i:s';

        $currentTimeAtStore = date($format, $currentStoreTimestamp);
        $yesterdayAtStore = date("Y-m-d", strtotime("yesterday", $currentStoreTimestamp));
        $yesterdayAtStoreFormatted = date($format, strtotime($yesterdayAtStore));

        $this->_helper->debug('store '.$store->getName().' $offset '. $offset);
        $this->_helper->debug('store '.$store->getName().' $currentTimeAtStore '. $currentTimeAtStore);
        $this->_helper->debug('store '.$store->getName().' $yesterdayAtStore '. $yesterdayAtStore);

        if($this->_checkIfReportExists ($store->getId(), $yesterdayAtStoreFormatted)) {
            return false;
        }

        //db data is stored in GMT so run reports with adjusted times
        $from = date($format, strtotime($yesterdayAtStore) - $offset);
        $to = date($format, strtotime("tomorrow",strtotime($yesterdayAtStore)) - $offset);
        $counts = Mage::getResourceModel('log/aggregation')->getCounts($from, $to, $store->getId());

        $this->_helper->debug('store '.$store->getName().' Report $from '. $from);
        $this->_helper->debug('store '.$store->getName().' Report $to '. $to);

        $abandonedCarts = $this->_gatherStoreAbandonedCarts($store->getId(), $from, $to);
        $reportData = array(
            'store_id' => $store->getId(),
            'description' => $store->getFrontendName().' ('.$store->getName().')',
            'time_zone'=> $store->getConfig('general/locale/timezone'),
            'dt'=> $yesterdayAtStoreFormatted,
            'dt_nice'=> Mage::helper('core')->formatDate($yesterdayAtStore, 'long'),
            'base_url' => $store->getConfig('web/unsecure/base_url'),
            'num_orders' => $this->_gatherStoreOrders($store->getId(), $from, $to),
            'revenue' => $this->_gatherStoreRevenue($store->getId(), $from, $to),
            'num_visitors' => $counts['customers'] + $counts['visitors'],
            'num_abandoned_carts'=> $abandonedCarts['num'],
            'revenue_abandoned_carts'=> $abandonedCarts['revenue'],
            'currency' => $currency
        );
        $reportData['revenue_nice'] = Mage::getModel('directory/currency')->load($currency)->formatTxt($reportData['revenue']);
        return $reportData;
    }

    private function _gatherStoreRevenue ($storeId, $from, $to)
    {
        return Mage::getResourceModel('foomanjirafe/report')->getStoreRevenue($storeId, $from, $to);
    }

    private function _gatherStoreOrders ($storeId, $from, $to)
    {
        return Mage::getResourceModel('foomanjirafe/report')->getStoreOrders($storeId, $from, $to);
    }

    /**
     *
     * retrieve number and value of carts that are active and haven't been converted to orders
     *
     * @param int $storeId
     * @param date $from
     * @param date $to
     * @return array('num','revenue')
     */
    private function _gatherStoreAbandonedCarts ($storeId, $from, $to)
    {
        return Mage::getResourceModel('foomanjirafe/report')->getStoreAbandonedCarts($storeId, $from, $to);
    }

    private function _checkIfReportExists ($storeId, $day)
    {
        return Mage::getResourceModel('foomanjirafe/report')->checkIfReportExists($storeId, $day);
    }

}