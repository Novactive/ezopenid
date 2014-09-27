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
        return array( 'link_openid' );
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
            case 'link_openid':
            {
                $operatorValue = $this->getOpenIDLink();
            }
                break;
        }
    }

    /**
     * Retrieve user logins in JSon format
     *
     * @return string
     */
    protected function getOpenIDLink()
    {
        $openidIni                 = eZINI::instance( 'ezopenid.ini' );
        $serverUrl                 = $openidIni->variable( 'OpenIDSettings', 'ServerUrl' );
        $openIDConsumer            = new LightOpenID( $serverUrl );
        $openIDConsumer->returnUrl = eZURI::transformURI( eZSys::requestURI(), false, 'full' );
        $openIDConsumer->required  = array( 'Email', 'FirstName', 'LastName' );
        $openIDConsumer->identity  = $serverUrl;
        $operatorValue             = $openIDConsumer->authUrl();

        return $operatorValue;
    }
}
