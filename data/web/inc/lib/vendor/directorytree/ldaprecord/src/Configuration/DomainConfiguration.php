<?php

namespace LdapRecord\Configuration;

use LdapRecord\LdapInterface;

class DomainConfiguration
{
    /**
     * The extended configuration options.
     */
    protected static array $extended = [];

    /**
     * The configuration options array.
     *
     * The default values for each key indicate the type of value it requires.
     */
    protected array $options = [
        // An array of LDAP hosts.
        'hosts' => [],

        // The global LDAP operation timeout limit in seconds.
        'timeout' => 5,

        // The LDAP version to utilize.
        'version' => 3,

        // The port to use for connecting to your hosts.
        'port' => LdapInterface::PORT,

        // The protocol to use for connecting to your hosts (ldap:// or ldaps://).
        'protocol' => null,

        // The base distinguished name of your domain.
        'base_dn' => '',

        // The username to use for binding.
        'username' => '',

        // The password to use for binding.
        'password' => '',

        // Whether to use SSL when connecting.
        'use_ssl' => false,

        // Whether to use TLS when connecting.
        'use_tls' => false,

        // Whether to use SASL when connecting.
        'use_sasl' => false,

        // Whether to allow password changes over plaintext.
        'allow_insecure_password_changes' => false,

        // SASL options
        'sasl_options' => [
            'mech' => null,
            'realm' => null,
            'authc_id' => null,
            'authz_id' => null,
            'props' => null,
        ],

        // Whether follow referrals is enabled when performing LDAP operations.
        'follow_referrals' => false,

        // Custom LDAP options.
        'options' => [],
    ];

    /**
     * Constructor.
     *
     * @throws ConfigurationException When an option value given is an invalid type.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, static::$extended);

        foreach ($options as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Extend the configuration with a custom option, or override an existing.
     */
    public static function extend(string $option, mixed $default = null): void
    {
        static::$extended[$option] = $default;
    }

    /**
     * Flush the extended configuration options.
     */
    public static function flushExtended(): void
    {
        static::$extended = [];
    }

    /**
     * Get all configuration options.
     */
    public function all(): array
    {
        return $this->options;
    }

    /**
     * Set a configuration option.
     *
     * @throws ConfigurationException When an option value given is an invalid type.
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->validate($key, $value)) {
            $this->options[$key] = $value;
        }
    }

    /**
     * Get the value for the specified configuration options.
     *
     * @throws ConfigurationException When the option specified does not exist.
     */
    public function get(string $key): mixed
    {
        if (! $this->has($key)) {
            throw new ConfigurationException("Option $key does not exist.");
        }

        return $this->options[$key];
    }

    /**
     * Checks if a configuration option exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Validate the configuration option.
     *
     * @throws ConfigurationException When an option value given is an invalid type.
     */
    protected function validate(string $key, mixed $value): bool
    {
        $default = $this->get($key);

        if (is_array($default)) {
            $validator = new Validators\ArrayValidator($key, $value);
        } elseif (is_int($default)) {
            $validator = new Validators\IntegerValidator($key, $value);
        } elseif (is_bool($default)) {
            $validator = new Validators\BooleanValidator($key, $value);
        } else {
            $validator = new Validators\StringOrNullValidator($key, $value);
        }

        return $validator->validate();
    }
}
