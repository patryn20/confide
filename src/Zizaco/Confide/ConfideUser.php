<?php namespace Zizaco\Confide;

use Illuminate\Auth\UserInterface;
use Awareness\Aware;

class ConfideUser extends Aware implements UserInterface {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Laravel application
     *
     * @var Illuminate\Foundation\Application
     */
    public static $app;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = array('password');

    /**
     * List of attribute names which should be hashed.
     *
     * @var array
     */
    public static $passwordAttributes = array('password');

    /**
     * This way the model will automatically replace the plain-text password
     * attribute (from $passwordAttributes) with the hash checksum on save
     *
     * @var bool
     */
    public $autoHashPasswordAttributes = true;

    /**
     * Aware validation rules
     *
     * @var array
     */
    public static $rules = array(
        'username' => 'required|alpha_dash|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|between:4,11|confirmed',
        'password_confirmation' => 'between:4,11',
    );

    /**
     * Rules for when updating a user.
     *
     * @var array
     */
    protected $updateRules = array(
        'username' => 'required|alpha_dash',
        'email' => 'required|email',
        'password' => 'between:4,11|confirmed',
        'password_confirmation' => 'between:4,11',
    );

    /**
     * Create a new ConfideUser instance.
     */
    public function __construct( array $attributes = array() )
    {
        parent::__construct( $attributes );

        if ( ! static::$app )
            static::$app = app();

        $this->table = static::$app['config']->get('auth.table');
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Confirm the user (usually means that the user)
     * email is valid.
     *
     * @return bool
     */
    public function confirm()
    {
        $this->confirmed = 1;

        // ConfideRepository will update the database
        static::$app['confide.repository']
            ->confirmUser( $this );

        return true;
    }

    /**
     * Send email with information about password reset
     *
     * @return string
     */
    public function forgotPassword()
    {
        // ConfideRepository will generate token (and save it into database)
        $token = static::$app['confide.repository']
            ->forgotPassword( $this );

        $view = static::$app['config']->get('confide::email_reset_password');

        $this->sendEmail( 'confide::confide.email.password_reset.subject', $view, array('user'=>$this, 'token'=>$token) );

        return true;
    }

    /**
     * Change user password
     *
     * @param $params
     * @return string
     */
    public function resetPassword( $params )
    {
        $password = array_get($params, 'password', '');
        $passwordConfirmation = array_get($params, 'password_confirmation', '');

        if ( $password == $passwordConfirmation )
        {
            return static::$app['confide.repository']
                ->changePassword( $this, static::$app['hash']->make($password) );
        }
        else{
            return false;
        }
    }

    /**
     * Overwrite the Aware save method. Saves model into
     * database
     *
     * @param array $rules
     * @param array $messages
     * @param closure $callback
     * @return bool
     */
    public function save( array $rules = array(), array $messages = array(), \Closure $callback = null )
    {
        $duplicated = false;

        if(! $this->id)
        {
            $duplicated = static::$app['confide.repository']->userExists( $this );
        }

        if(! $duplicated)
        {
            $result = $this->real_save( $rules, $messages, $callback );    

            if($result)
            {
                $this->afterSave(); // Run afterSave method
            }

            return $result;
        }
        else
        {
            $this->getErrors();

            $this->errorBag->add(
                'duplicated',
                static::$app['translator']->get('confide::confide.alerts.duplicated_credentials')
            );

            return false;
        }
    }

    /**
     * Aware method overloading:
     * Before save the user. Generate a confirmation
     * code if is a new user.
     *
     * @return bool
     */
    public function onSave()
    {
        if ( empty($this->id) )
        {
            $this->confirmation_code = md5( uniqid(mt_rand(), true) );
        }

        /*
         * Remove password_confirmation field before save to
         * database.
         */
        if ( isset($this->password_confirmation) )
        {
            unset( $this->password_confirmation );
        }

        return true;
    }

    /**
     * After save, delivers the confirmation link email.
     * code if is a new user.
     *
     * @param bool $success
     * @return bool
     */
    public function afterSave()
    {
        if ( ! $this->confirmed )
        {
            $view = static::$app['config']->get('confide::email_account_confirmation');

            $this->sendEmail( 'confide::confide.email.account_confirmation.subject', $view, array('user' => $this) );
        }

        return true;
    }

    /**
     * Runs the real eloquent/aware save method or returns
     * true if it's under testing. Because Eloquent
     * and Aware save methods are not Confide's
     * responsibility.
     *
     * @param array $rules
     * @param array $messages
     * @param closure $callback
     * @return bool
     */
    protected function real_save( array $rules = array(), array $messages = array(), \Closure $callback = null )
    {
        if ( defined('CONFIDE_TEST') )
        {
            $this->onSave();
            return true;
        }
        else{

            /*
             * This will make sure that a non modified password
             * will not trigger validation error.
             */
            if( empty($rules) && $this->password == $this->getOriginal('password') )
            {
                $rules = static::$rules;
                $rules['password'] = 'required';
            }

            return parent::save( $rules, $messages, $callback );
        }
    }

    /**
     * Add the namespace 'confide::' to view hints.
     * this makes possible to send emails using package views from
     * the command line.
     *
     * @return void
     */
    protected static function fixViewHint()
    {
        if (isset(static::$app['view.finder']))
            static::$app['view.finder']->addNamespace('confide', __DIR__.'/../../views');
    }

    /**
     * Send email using the lang sentence as subject and the viewname
     *
     * @param mixed $subject_translation
     * @param mixed $view_name
     * @param array $params
     * @return voi.
     */
    protected function sendEmail( $subject_translation, $view_name, $params = array() )
    {
        if ( static::$app['config']->getEnvironment() == 'testing' )
            return;

        static::fixViewHint();

        $user = $this;

        static::$app['mailer']->send($view_name, $params, function($m) use ($subject_translation, $user)
        {
            $m->to( $user->email )
            ->subject( ConfideUser::$app['translator']->get($subject_translation) );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Deprecated methods
    |--------------------------------------------------------------------------
    |
    */
   
   /**
     * [Deprecated] Alias of save but uses updateRules instead of rules.
     *
     * @deprecated
     * @param array $rules
     * @param array $messages
     * @param closure $callback
     * @return bool
     */
    public function amend( array $rules = array(), array $messages = array(), \Closure $callback = null )
    {
        if(empty($rules))
            $rules = $this->updateRules;

        return $this->save( $rules, $messages, $options, $callback );
    }

    /**
     * [Deprecated]
     * 
     * @deprecated
     */
    public function getUpdateRules()
    {
        return $this->updateRules;
    }

    /**
     * [Deprecated] Parses the two given users and compares the unique fields.
     * 
     * @deprecated
     * @param $oldUser
     * @param $updatedUser
     * @param array $rules
     */
    public function prepareRules($oldUser, $updatedUser, $rules=array())
    {
        if(empty($rules)) {
            $rules = $this->getRules();
        }

        foreach($rules as $rule => $validation) {
            // get the rules with unique.
            if (strpos($validation, 'unique')) {
                // Compare old vs new
                if($oldUser->$rule != $updatedUser->$rule) {
                    // Set update rule to creation rule
                    $updateRules = $this->getUpdateRules();
                    $updateRules[$rule] = $validation;
                    $this->setUpdateRules($updateRules);
                }
            }
        }
    }

    /**
     * [Deprecated]
     * 
     * @deprecated
     */
    public function getRules()
    {
        return self::$rules;
    }

    /**
     * [Deprecated]
     * 
     * @deprecated
     */
    public function setUpdateRules($set)
    {
        $this->updateRules = $set;
    }

    /**
     * [Deprecated] Find an user by it's credentials. Perform a 'where' within
     * the fields contained in the $identityColumns.
     *
     * @deprecated Use ConfideRepository getUserByIdentity instead.
     * @param  array $credentials      An array containing the attributes to search for
     * @param  mixed $identityColumns  Array of attribute names or string (for one atribute)
     * @return ConfideUser             User object
     */
    public function getUserFromCredsIdentity($credentials, $identity_columns = array('username', 'email'))
    {
        return static::$app['confide.repository']->getUserByIdentity($credentials, $identity_columns);
    }

    /**
     * [Deprecated] Checks if an user exists by it's credentials. Perform a 'where' within
     * the fields contained in the $identityColumns.
     * 
     * @deprecated Use ConfideRepository getUserByIdentity instead.
     * @param  array $credentials      An array containing the attributes to search for
     * @param  mixed $identityColumns  Array of attribute names or string (for one atribute)
     * @return boolean                 Exists?
     */
    public function checkUserExists($credentials, $identity_columns = array('username', 'email'))
    {
        $user = static::$app['confide.repository']->getUserByIdentity($credentials, $identity_columns);

        if ($user) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [Deprecated] Checks if an user is confirmed by it's credentials. Perform a 'where' within
     * the fields contained in the $identityColumns.
     * 
     * @deprecated Use ConfideRepository getUserByIdentity instead.
     * @param  array $credentials      An array containing the attributes to search for
     * @param  mixed $identityColumns  Array of attribute names or string (for one atribute)
     * @return boolean                 Is confirmed?
     */
    public function isConfirmed($credentials, $identity_columns = array('username', 'email'))
    {
        $user = static::$app['confide.repository']->getUserByIdentity($credentials, $identity_columns);

        if (! is_null($user) and $user->confirmed) {
            return true;
        } else {
            return false;
        }
    }
}
