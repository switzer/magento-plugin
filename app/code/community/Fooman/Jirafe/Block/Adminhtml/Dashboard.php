<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @package     Fooman_Jirafe
 * @copyright   Copyright (c) 2010 Jirafe Inc (http://www.jirafe.com)
 * @copyright   Copyright (c) 2010 Fooman Limited (http://www.fooman.co.nz)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fooman_Jirafe_Block_Adminhtml_Dashboard extends Mage_Adminhtml_Block_Dashboard
{

    public function __construct ()
    {
        parent::__construct();
        if (Mage::helper('foomanjirafe')->getStoreConfig('isDashboardActive')) {
            $this->setTemplate('fooman/jirafe/dashboard.phtml');
        }
    }

    public function getHeaderWidth ()
    {
        return 'width:50%;';
    }

    public function getHeaderText ()
    {
        return Mage::helper('foomanjirafe')->__('Dashboard');
    }

    public function getHeaderHtml ()
    {
        return '<h3 class="' . $this->getHeaderCssClass() . '">' . $this->getHeaderText() . '</h3>';
    }

    public function getDashboardApiUrl () {
        return Fooman_Jirafe_Model_Api::JIRAFE_UI_URL;
    }

    public function getJirafeUserToken () {
        return 'anonymous';
        return Mage::getSingleton('admin/session')->getUser()->getJirafeUserToken();
    }
}