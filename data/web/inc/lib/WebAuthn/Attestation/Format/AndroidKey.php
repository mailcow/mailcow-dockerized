<?php

namespace lbuchs\WebAuthn\Attestation\Format;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;
use lbuchs\WebAuthn\WebAuthnException;
use lbuchs\WebAuthn\Binary\ByteBuffer;

class AndroidKey extends FormatBase {
    private $_alg;
    private $_signature;
    private $_x5c;

    public function __construct($AttestionObject, AuthenticatorData $authenticatorData) {
        parent::__construct($AttestionObject, $authenticatorData);

        // check u2f data
        $attStmt = $this->_attestationObject['attStmt'];

        if (!\array_key_exists('alg', $attStmt) || $this->_getCoseAlgorithm($attStmt['alg']) === null) {
            throw new WebAuthnException('unsupported alg: ' . $attStmt['alg'], WebAuthnException::INVALID_DATA);
        }

        if (!\array_key_exists('sig', $attStmt) || !\is_object($attStmt['sig']) || !($attStmt['sig'] instanceof ByteBuffer)) {
            throw new WebAuthnException('no signature found', WebAuthnException::INVALID_DATA);
        }

        if (!\array_key_exists('x5c', $attStmt) || !\is_array($attStmt['x5c']) || \count($attStmt['x5c']) < 1) {
            throw new WebAuthnException('invalid x5c certificate', WebAuthnException::INVALID_DATA);
        }

        if (!\is_object($attStmt['x5c'][0]) || !($attStmt['x5c'][0] instanceof ByteBuffer)) {
            throw new WebAuthnException('invalid x5c certificate', WebAuthnException::INVALID_DATA);
        }

        $this->_alg = $attStmt['alg'];
        $this->_signature = $attStmt['sig']->getBinaryString();
        $this->_x5c = $attStmt['x5c'][0]->getBinaryString();

        if (count($attStmt['x5c']) > 1) {
            for ($i=1; $i<count($attStmt['x5c']); $i++) {
                $this->_x5c_chain[] = $attStmt['x5c'][$i]->getBinaryString();
            }
            unset ($i);
        }
    }


    /*
     * returns the key certificate in PEM format
     * @return string
     */
    public function getCertificatePem() {
        return $this->_createCertificatePem($this->_x5c);
    }

    /**
     * @param string $clientDataHash
     */
    public function validateAttestation($clientDataHash) {
        $publicKey = \openssl_pkey_get_public($this->getCertificatePem());

        if ($publicKey === false) {
            throw new WebAuthnException('invalid public key: ' . \openssl_error_string(), WebAuthnException::INVALID_PUBLIC_KEY);
        }

        // Verify that sig is a valid signature over the concatenation of authenticatorData and clientDataHash
        // using the attestation public key in attestnCert with the algorithm specified in alg.
        $dataToVerify = $this->_authenticatorData->getBinary();
        $dataToVerify .= $clientDataHash;

        $coseAlgorithm = $this->_getCoseAlgorithm($this->_alg);

        // check certificate
        return \openssl_verify($dataToVerify, $this->_signature, $publicKey, $coseAlgorithm->openssl) === 1;
    }

    /**
     * validates the certificate against root certificates
     * @param array $rootCas
     * @return boolean
     * @throws WebAuthnException
     */
    public function validateRootCertificate($rootCas) {
        $chainC = $this->_createX5cChainFile();
        if ($chainC) {
            $rootCas[] = $chainC;
        }

        $v = \openssl_x509_checkpurpose($this->getCertificatePem(), -1, $rootCas);
        if ($v === -1) {
            throw new WebAuthnException('error on validating root certificate: ' . \openssl_error_string(), WebAuthnException::CERTIFICATE_NOT_TRUSTED);
        }
        return $v;
    }
}

