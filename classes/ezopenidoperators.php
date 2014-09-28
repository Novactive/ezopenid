<?php
/**
 * eZOpenID extension
 *
 * @category  eZpublish
 * @package   eZpublish.eZopenid
 * @author    Novactive <contact@novactive.com>
 * @copyright 2014 Novactive
 * @license   OSL-3
 */

/**
 * OpenID operators class
 *
 * @category  eZpublish
 * @package   eZpublish.eZopenid
 * @author    Novactive <contact@novactive.com>
 * @copyright 2014 Novactive
 */
class eZOpenIDOperators
{
    /**
     * class constructor
     */
    function eZOpenIDOperators()
    {

    }

    /**
     * Retrieve class operators list
     *
     * @return array
     */
    function operatorList()
    {
        return array( 'openid_login_url' );
    }

    /**
     * Define if the operators have named parameters
     *
     * @return bool
     */
    function namedParameterPerOperator()
    {
        return false;
    }

    /**
     *
     *
     * @param $tpl
     * @param $operatorName
     * @param $operatorParameters
     * @param $rootNamespace
     * @param $currentNamespace
     * @param $operatorValue
     * @param $namedParameters
     */
    function modify(
        $tpl,
        $operatorName,
        $operatorParameters,
        &$rootNamespace,
        &$currentNamespace,
        &$operatorValue,
        &$namedParameters
    ) {
        switch ( $operatorName )
        {
            case 'openid_login_url':
            {
                $operatorValue = $this->getOpenIDLoginUrl();
            }
                break;
        }
    }

    /**
     * Retrieve user logins in JSon format
     *
     * @return string
     */
    protected function getOpenIDLoginUrl()
    {
        $openidIni                 = eZINI::instance( 'ezopenid.ini' );
        $serverUrl                 = $openidIni->variable( 'OpenIDSettings', 'ServerUrl' );
        $returnUrl                 = eZSys::serverURL() . eZSys::requestURI();
        $openIDConsumer            = new LightOpenID( eZSys::hostname() );
        $openIDConsumer->returnUrl = $returnUrl;
        $openIDConsumer->required  = array( 'contact/email', 'namePerson/first', 'namePerson/last' );
        $openIDConsumer->identity  = $serverUrl;
        $operatorValue             = $openIDConsumer->authUrl();

        return $operatorValue;
    }
}
