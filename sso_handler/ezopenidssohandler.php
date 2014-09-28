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
 * OpenId SSO handler class
 *
 * @category  eZpublish
 * @package   eZpublish.eZopenid
 * @author    Novactive <contact@novactive.com>
 * @copyright 2014 Novactive
 */
class eZOpenIDSSOHandler
{
    /**
     * Content of the site.ini configuration
     * @var eZINI|string $_ini
     */
    protected $_ini = null;

    /**
     * Content of the ezopenid.ini configuration
     * @var eZINI|null $_openidIni
     */
    protected $_openidIni = null;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_ini       = eZINI::instance( 'site.ini' );
        $this->_openidIni = eZINI::instance( 'ezopenid.ini' );
    }

    /**
     * Login user from data retrieved from OpenID server
     *
     * @return false / eZUser
     */
    public function handleSSOLogin()
    {
        $user           = false;
        $returnUrl      = eZSys::serverURL() . eZSys::requestURI();
        $openIDConsumer = new LightOpenID( eZSys::hostname() );
        $required       = array( 'contact/email' );
        foreach ( $this->_openidIni->variable( 'OpenIDSettings', 'AttributesMapping' ) as $identifier )
        {
            $required[] = preg_replace( '/_/', "/", $identifier );
        }
        $openIDConsumer->required  = $required;
        $openIDConsumer->returnUrl = $returnUrl;
        $openIDConsumer->data      = $_GET;

        if ( $openIDConsumer->validate() )
        {
            $aLoginIdentity = explode( '/', $openIDConsumer->data['openid_identity'] );
            $loginIdentity  = array_pop( $aLoginIdentity );

            // Retrieve the local user, with the login provided by CAS
            $user                = eZUser::fetchByName( $loginIdentity );
            $createUserIfMissing = $this->_openidIni->variable( 'OpenIDSettings', 'CreateUserIfMissing' );
            if ( !$user && $createUserIfMissing != 'false' )
            {
                // Create the user
                $user = $this->_createUser( $openIDConsumer );
            }
            else
            {
                if ( $user )
                {
                    // Check if the user information should be updated
                    $this->_updateUser( $user, $openIDConsumer );
                }
            }
            if ( $user )
            {
                $userId = $user->attribute( 'contentobject_id' );
                eZUser::updateLastVisit( $userId );
                eZUser::setFailedLoginAttempts( $userId, 0 );
            }
            else
            {
                $user = false;
            }
        }

        return $user;
    }

    /**
     * Create the user object with information retrieved from openId
     *
     * @param LightOpenID $openIdConsumer openId consumer object
     *
     * @return eZUser
     */
    protected function _createUser( LightOpenID $openIdConsumer )
    {
        $attributeMapping = $this->_openidIni->variable( 'OpenIDSettings', 'AttributeMapping' );
        $login            = array_pop( explode( '/', $openIdConsumer->data['openid_identity'] ) );
        $email            = $openIdConsumer->data['openid_ext1_value_contact_email'];
        foreach ( $attributeMapping as $ezIdentifier => $openIdIdentifier )
        {
            $userAttributes[$ezIdentifier]['value'] = $openIdConsumer->data['openid_ext1_value_' . $openIdIdentifier];
        }

        $contentObject = $this->_initUserObject();
        $userID        = $contentObjectID = $contentObject->attribute( 'id' );

        $versionNumber = $this->_manageUserObjectAttributes( $userAttributes, $contentObject );
        $this->_manageUserPlacement( $contentObjectID );

        $user = $this->_initUser( $userID, $login, $email );

        eZOperationHandler::execute(
            'content',
            'publish',
            array( 'object_id' => $contentObjectID, 'version' => $versionNumber )
        );

        return $user;
    }

    /**
     * Update the user information if required
     *
     * @param eZUser      $currentUser    current user object
     * @param LightOpenID $openIdConsumer openId consumer object
     *
     * @return void
     */
    protected function _updateUser( $currentUser, LightOpenID $openIdConsumer )
    {
        $attributeMapping = $this->_openidIni->variable( 'OpenIDSettings', 'AttributeMapping' );
        $email            = $openIdConsumer->data['openid_ext1_value_openid_email'];
        foreach ( $attributeMapping as $ezIdentifier => $openIdIdentifier )
        {
            $userAttributes[$ezIdentifier]['value'] = $openIdConsumer->data['openid_ext1_value_' . $openIdIdentifier];
        }

        $contentObjectID = $currentUser->attribute( 'contentobject_id' );
        $contentObject   = eZContentObject::fetch( $contentObjectID );

        $versionNumber = $this->_manageUserObjectAttributes( $userAttributes, $contentObject, true );

        if ( $email != $currentUser->attribute( 'email' ) )
        {
            $currentUser->setAttribute( 'email', $email );
            $currentUser->store();
        }

        if ( $versionNumber )
        {
            eZOperationHandler::execute(
                'content',
                'publish',
                array(
                    'object_id' => $contentObjectID,
                    'version'   => $versionNumber
                )
            );
        }
    }

    /**
     * Instantiate the content object for user
     *
     * @return eZContentObject
     */
    protected function _initUserObject()
    {
        $userClassID      = $this->_ini->variable( 'UserSettings', 'UserClassID' );
        $userCreatorID    = $this->_ini->variable( 'UserSettings', 'UserCreatorID' );
        $defaultSectionID = $this->_ini->variable( 'UserSettings', 'DefaultSectionID' );
        $class            = eZContentClass::fetch( $userClassID );
        $contentObject    = $class->instantiate( $userCreatorID, $defaultSectionID );
        $contentObject->store();

        return $contentObject;
    }

    /**
     * Create the eZUser object and specify the required attributes
     *
     * @param integer $userID content object ID for user
     * @param string  $login  user login
     * @param string  $email  user email
     *
     * @return eZUser
     */
    protected function _initUser( $userID, $login, $email )
    {
        $user = eZUser::create( $userID );
        $user->setAttribute( 'login', $login );
        $user->setAttribute( 'email', $email );
        $user->setAttribute( 'password_hash', '' );
        $user->setAttribute( 'password_hash_type', 0 );
        $user->store();

        return $user;
    }

    /**
     * Set the object attributes, for lastname, firstname and object name
     *
     * @param array           $userAttributes user attributes array
     * @param eZContentObject $contentObject  content object for user
     * @param boolean         $updateMode     indicate if we are updating the current user account
     *                                        or creating a new one
     *
     * @return integer/false
     */
    protected function _manageUserObjectAttributes( $userAttributes, $contentObject, $updateMode = false )
    {
        if ( !$updateMode )
        {
            $versionNumber = 1;
            $version       = $contentObject->version( $versionNumber );
            $version->setAttribute( 'modified', time() );
            $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
            $version->store();
        }
        else
        {
            $versionNumber = false;
            $version       = $contentObject->attribute( 'current' );
        }
        $userDataChanged = false;

        $contentObjectAttributes = $version->contentObjectAttributes();

        // Retrieve object attribute obejct for each user attribute to fill
        foreach ( $contentObjectAttributes as $attribute )
        {
            foreach ( $userAttributes as $attributeIdentifier => $attributeData )
            {
                if ( $attribute->attribute( 'contentclass_attribute_identifier' ) == $attributeIdentifier )
                {
                    $userAttributes[$attributeIdentifier]['attribute'] = $attribute;
                    // Indicate if data has changed for at least one attribute
                    // We need this in update mode in order not to update the user
                    // if no user attrbute has changed
                    $userDataChanged = ( $attributeData['value'] !=
                                         $attribute->attribute( 'data_text' ) ) ? true : $userDataChanged;
                }
            }
        }

        // Create a new object version if in update mode and at least one user attribute
        // has to be updated
        if ( $updateMode && $userDataChanged )
        {
            $version                 = $contentObject->createNewVersion();
            $versionNumber           = $version->attribute( 'version' );
            $contentObjectAttributes = $version->contentObjectAttributes();
            foreach ( $contentObjectAttributes as $attribute )
            {
                foreach ( $userAttributes as $attributeIdentifier => $attributeData )
                {
                    if ( $attribute->attribute( 'contentclass_attribute_identifier' ) == $attributeIdentifier )
                    {
                        $userAttributes[$attributeIdentifier]['attribute'] = $attribute;
                    }
                }
            }
        }

        // Complete object data if not on update mode or if a least one user attribute
        // has to be updated
        if ( !$updateMode || $userDataChanged )
        {
            foreach ( $userAttributes as $attributeData )
            {
                $attributeData['attribute']->setAttribute( 'data_text', $attributeData['value'] );
                $attributeData['attribute']->store();
            }

            $contentClass = $contentObject->attribute( 'content_class' );
            $name         = $contentClass->contentObjectName( $contentObject );
            $contentObject->setName( $name );
        }

        return $versionNumber;
    }

    /**
     * Prepare node assignments for publishing new user
     *
     * @param integer $contentObjectID content object ID for user
     *
     * @return void
     */
    protected function _manageUserPlacement( $contentObjectID )
    {
        $userGroupIDs         = $this->_openidIni->variable( 'OpenIDSettings', 'GroupAssignments' );
        $defaultUserPlacement = $this->_ini->variable( 'UserSettings', 'DefaultUserPlacement' );
        $userGroupIds[]       = $defaultUserPlacement;
        foreach ( $userGroupIDs as $userGroupID )
        {
            $newNodeAssignment = eZNodeAssignment::create(
                array(
                    'contentobject_id'      => $contentObjectID,
                    'contentobject_version' => 1,
                    'parent_node'           => $userGroupID,
                    'is_main'               => ( $defaultUserPlacement == $userGroupID ? 1 : 0 )
                )
            );
            $newNodeAssignment->setAttribute( 'parent_remote_id', uniqid( 'OPENID_' ) );
            $newNodeAssignment->store();
        }
    }
}
