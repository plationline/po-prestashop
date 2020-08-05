<?php
    namespace PlatiOnlinePO6\Inc\Libraries\sylouuu\Curl\Method;

/**
     * Put
     *
     * @author sylouuu
     * @link https://github.com/sylouuu/php-curl
     * @version 0.8.1
     * @license MIT
     */
    class Put extends \PlatiOnlinePO6\Inc\Libraries\sylouuu\Curl\Curl
    {
        /**
         * Constructor
         *
         * @param string $url
         * @param array $options
         */
        public function __construct($url, $options = null)
        {
            parent::__construct($url, $options);

            $this->prepare();
        }

        /**
         * Prepare the request
         */
        public function prepare()
        {
            $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'PUT');

            if (isset($this->options['data'])) {
                // Data
                $data = (isset($this->options['is_payload']) && $this->options['is_payload'] === true) ? json_encode($this->options['data']) : http_build_query($this->options['data']);

                $this->setCurlOption(CURLOPT_POSTFIELDS, $data);
            }
        }
    }
