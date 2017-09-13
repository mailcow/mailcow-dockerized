<?php
/**
 * Copyright (c) 2014 Yubico AB
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above
 *     copyright notice, this list of conditions and the following
 *     disclaimer in the documentation and/or other materials provided
 *     with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once(__DIR__ . '/../src/u2flib_server/U2F.php');

class U2FTest extends \PHPUnit_Framework_TestCase {
  /** @var u2flib_server\U2F */
  private $u2f;

  public function setUp() {
    $this->u2f = new u2flib_server\U2F("http://demo.example.com");
  }

  public function testGetRegisterData() {
    list($reg, $signData) = $this->u2f->getRegisterData();
    $this->assertJsonStringEqualsJsonString(json_encode(array()), json_encode($signData));
    $this->assertEquals('U2F_V2', $reg->version);
    $this->assertObjectHasAttribute('challenge', $reg);
    $this->assertEquals('http://demo.example.com', $reg->appId);
  }

  public function testDoRegister() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9", "errorCode": 0 }');
    $reg = $this->u2f->doRegister($req, $resp);
    $this->assertEquals('CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w', $reg->keyHandle);
    $this->assertEquals('BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y/yaFORPUe3c=', $reg->publicKey);
    $this->assertEquals('MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp/VRZHOwd2NZNzpnB9ePNKvUaWCGK/gN+cynnYFdwJ75iSgMVYb/RnFcdPwnsBzBU68hbhTnu/FvJxWo7rZJ2q7qXpA10eLVXJr4/4oSXEk9I/0IIHqOP98Ck/fAoI5gYI7ygndyqoPJ/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh/h7oKEKamCWk19dJp5jHQmumkHlvQhH/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg/0J+xOb4zl6a1z65nae4OTj7628/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==', $reg->certificate);
    $this->assertLessThan(0, $reg->counter);
  }

  public function testDoRegisterNoCert() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $reg = $this->u2f->doRegister($req, $resp, false);
    $this->assertEquals('CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w', $reg->keyHandle);
    $this->assertEquals('BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y/yaFORPUe3c=', $reg->publicKey);
    $this->assertEquals('', $reg->certificate);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_ATTESTATION_VERIFICATION
   */
  public function testDoRegisterAttestFail() {
    $this->u2f = new u2flib_server\U2F("http://demo.example.com", __DIR__ . "/../tests/certs");
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp, true);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_ATTESTATION_SIGNATURE
   */
  public function testDoRegisterFail2() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8NwW=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp, false);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_UNMATCHED_CHALLENGE
   */
  public function testDoRegisterFail() {
    $req = json_decode('{"version":"U2F_V2","challenge":"YKA0X075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp, false);
  }

  public function testDoRegisterAttest() {
    $this->u2f = new u2flib_server\U2F("http://demo.example.com", __DIR__ . "/../tests/certs");
    $req = json_decode('{"version":"U2F_V2","challenge":"5CBRhGBb2CXSum71GNREBGft7yz9g1jZO7JTkHGFsVY","appId":"http:\/\/demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQRX1gfcG-ofTlk9rjB9spsIMrmT9ba0DLto5fzk8FDB05ModNU2sWAqoQRemYiUrILQdbNGpN_aHA0_oq8kcd_XQCK-Ut0PWaOtz43t0aAV04U788e-dvpeqLtHxtINjgmutKM8_GJQ7F-3W0dogUjSANuRYRdkkSEHPcVdLSkpyfowggIbMIIBBaADAgECAgRAxBIlMAsGCSqGSIb3DQEBCzAuMSwwKgYDVQQDEyNZdWJpY28gVTJGIFJvb3QgQ0EgU2VyaWFsIDQ1NzIwMDYzMTAgFw0xNDA4MDEwMDAwMDBaGA8yMDUwMDkwNDAwMDAwMFowKjEoMCYGA1UEAwwfWXViaWNvIFUyRiBFRSBTZXJpYWwgMTA4NjU5MTUyNTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABK2iSVV7KGNEdPE-oHGvobNnHVw6ZZ6vB3jNIYB1C4t32OucHzMweHqM5CAMSMDHtfp1vuJYaiQSk7jb6M48WtejEjAQMA4GCisGAQQBgsQKAQEEADALBgkqhkiG9w0BAQsDggEBAVg0BoEHEEp4LJLYPYFACRGS8WZiXkCA8crYLgGnzvfKXwPwyKJlUzYxxv5xoRrl5zjkIUXhZ4mnHZVsnj9EY_VGDuRRzKX7YtxTZpFZn7ej3abjLhckTkkQ_AhUkmP7VuK2AWLgYsS8ejGUqughBsKvh_84uxTAEr5BS-OGg2yi7UIjd8W0nOCc6EN8d_8wCiPOjt2Y_-TKpLLTXKszk4UnWNzRdxBThmBBprJBZbF1VyVRvJm5yRLBpth3G8KMvrt4Nu3Ecoj_Q154IJpWe1Dp1upDFLOG9nWCRQk25Y264k9BDISfqs-wHvUjIo2iDnKl5UVoauTWaT7M6KuEwl4wRAIgYUVjS_yTwJAtF35glSbf9Et-5tJzlHOeAqmbACd6pwsCIE0MkTR5XNQoO4XqDaUZCXmadWu8yU1gfE7AJI9JUUcc", "clientData": "eyAiY2hhbGxlbmdlIjogIjVDQlJoR0JiMkNYU3VtNzFHTlJFQkdmdDd5ejlnMWpaTzdKVGtIR0ZzVlkiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $reg = $this->u2f->doRegister($req, $resp, true);
    $this->assertEquals('Ir5S3Q9Zo63Pje3RoBXThTvzx752-l6ou0fG0g2OCa60ozz8YlDsX7dbR2iBSNIA25FhF2SRIQc9xV0tKSnJ-g', $reg->keyHandle);
    $this->assertEquals('BFfWB9wb6h9OWT2uMH2ymwgyuZP1trQMu2jl/OTwUMHTkyh01TaxYCqhBF6ZiJSsgtB1s0ak39ocDT+iryRx39c=', $reg->publicKey);
    $this->assertEquals('MIICGzCCAQWgAwIBAgIEQMQSJTALBgkqhkiG9w0BAQswLjEsMCoGA1UEAxMjWXViaWNvIFUyRiBSb290IENBIFNlcmlhbCA0NTcyMDA2MzEwIBcNMTQwODAxMDAwMDAwWhgPMjA1MDA5MDQwMDAwMDBaMCoxKDAmBgNVBAMMH1l1YmljbyBVMkYgRUUgU2VyaWFsIDEwODY1OTE1MjUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAAStoklVeyhjRHTxPqBxr6GzZx1cOmWerwd4zSGAdQuLd9jrnB8zMHh6jOQgDEjAx7X6db7iWGokEpO42+jOPFrXoxIwEDAOBgorBgEEAYLECgEBBAAwCwYJKoZIhvcNAQELA4IBAQBYNAaBBxBKeCyS2D2BQAkRkvFmYl5AgPHK2C4Bp873yl8D8MiiZVM2Mcb+caEa5ec45CFF4WeJpx2VbJ4/RGP1Rg7kUcyl+2LcU2aRWZ+3o92m4y4XJE5JEPwIVJJj+1bitgFi4GLEvHoxlKroIQbCr4f/OLsUwBK+QUvjhoNsou1CI3fFtJzgnOhDfHf/MAojzo7dmP/kyqSy01yrM5OFJ1jc0XcQU4ZgQaayQWWxdVclUbyZuckSwabYdxvCjL67eDbtxHKI/0NeeCCaVntQ6dbqQxSzhvZ1gkUJNuWNuuJPQQyEn6rPsB71IyKNog5ypeVFaGrk1mk+zOirhMJe', $reg->certificate);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_PUBKEY_DECODE
   */
  public function testDoRegisterBadKeyInCert() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABdsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_PUBKEY_DECODE
   */
  public function testDoRegisterBadKey() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQMtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $request of doRegister() method only accepts object.
   */
  public function testDoRegisterInvalidRequest() {
    $req = 'request';
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $response of doRegister() method only accepts object.
   */
  public function testDoRegisterInvalidResponse() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = 'response';
    $this->u2f->doRegister($req, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_BAD_UA_RETURNING
   */
  public function testDoRegisterUAError() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{"errorCode": "4"}');
    $this->u2f->doRegister($req, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $include_cert of doRegister() method only accepts boolean.
   */
  public function testDoRegisterInvalidInclude_cert() {
    $req = json_decode('{"version":"U2F_V2","challenge":"yKA0x075tjJ-GE7fKTfnzTOSaNUOWQxRd9TWz5aFOg8","appId":"http://demo.example.com"}');
    $resp = json_decode('{ "registrationData": "BQQtEmhWVgvbh-8GpjsHbj_d5FB9iNoRL8mNEq34-ANufKWUpVdIj6BSB_m3eMoZ3GqnaDy3RA5eWP8mhTkT1Ht3QAk1GsmaPIQgXgvrBkCQoQtMFvmwYPfW5jpRgoMPFxquHS7MTt8lofZkWAK2caHD-YQQdaRBgd22yWIjPuWnHOcwggLiMIHLAgEBMA0GCSqGSIb3DQEBCwUAMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBDQTAeFw0xNDA1MTUxMjU4NTRaFw0xNDA2MTQxMjU4NTRaMB0xGzAZBgNVBAMTEll1YmljbyBVMkYgVGVzdCBFRTBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABNsK2_Uhx1zOY9ym4eglBg2U5idUGU-dJK8mGr6tmUQflaNxkQo6IOc-kV4T6L44BXrVeqN-dpCPr-KKlLYw650wDQYJKoZIhvcNAQELBQADggIBAJVAa1Bhfa2Eo7TriA_jMA8togoA2SUE7nL6Z99YUQ8LRwKcPkEpSpOsKYWJLaR6gTIoV3EB76hCiBaWN5HV3-CPyTyNsM2JcILsedPGeHMpMuWrbL1Wn9VFkc7B3Y1k3OmcH1480q9RpYIYr-A35zKedgV3AnvmJKAxVhv9GcVx0_CewHMFTryFuFOe78W8nFajutknarupekDXR4tVcmvj_ihJcST0j_Qggeo4_3wKT98CgjmBgjvKCd3Kqg8n9aSDVWyaOZsVOhZj3Fv5rFu895--D4qiPDETozJIyliH-HugoQpqYJaTX10mnmMdCa6aQeW9CEf-5QmbIP0S4uZAf7pKYTNmDQ5z27DVopqaFw00MIVqQkae_zSPX4dsNeeoTTXrwUGqitLaGap5ol81LKD9JdP3nSUYLfq0vLsHNDyNgb306TfbOenRRVsgQS8tJyLcknSKktWD_Qn7E5vjOXprXPrmdp7g5OPvrbz9QkWa1JTRfo2n2AXV02LPFc-UfR9bWCBEIJBxvmbpmqt0MnBTHWnth2b0CU_KJTDCY3kAPLGbOT8A4KiI73pRW-e9SWTaQXskw3Ei_dHRILM_l9OXsqoYHJ4Dd3tbfvmjoNYggSw4j50l3unI9d1qR5xlBFpW5sLr8gKX4bnY4SR2nyNiOQNLyPc0B0nW502aMEUCIQDTGOX-i_QrffJDY8XvKbPwMuBVrOSO-ayvTnWs_WSuDQIgZ7fMAvD_Ezyy5jg6fQeuOkoJi8V2naCtzV-HTly8Nww=", "clientData": "eyAiY2hhbGxlbmdlIjogInlLQTB4MDc1dGpKLUdFN2ZLVGZuelRPU2FOVU9XUXhSZDlUV3o1YUZPZzgiLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5maW5pc2hFbnJvbGxtZW50IiB9" }');
    $this->u2f->doRegister($req, $resp, 'bar');
  }

  public function testGetAuthenticateData() {
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg=="}'));
    $data = $this->u2f->getAuthenticateData($regs);
    $inst = $data[0];
    $this->assertEquals("U2F_V2", $inst->version);
    $this->assertObjectHasAttribute("challenge", $inst);
    $this->assertEquals('CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w', $inst->keyHandle);
    $this->assertEquals('http://demo.example.com', $inst->appId);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $registrations of getAuthenticateData() method only accepts array of object.
   */
  public function testGetAuthenticateDataInvalidRegistrations2() {
    $regs = array('YubiKey NEO', 'YubiKey Standard');
    $data = $this->u2f->getAuthenticateData($regs);
  }

  public function testDoAuthenticate() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":3}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w", "errorCode": 0 }');
    $data = $this->u2f->doAuthenticate($reqs, $regs, $resp);
    $this->assertEquals(4, $data->counter);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_COUNTER_TOO_LOW
   */
  public function testDoAuthenticateCtrFail() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":5}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_AUTHENTICATION_FAILURE
   */
  public function testDoAuthenticateFail() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg=="}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAnG==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_NO_MATCHING_REQUEST
   */
  public function testDoAuthenticateWrongReq() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"cTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg=="}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_NO_MATCHING_REGISTRATION
   */
  public function testDoAuthenticateWrongReg() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"cTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg=="}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_PUBKEY_DECODE
   */
  public function testDoAuthenticateBadKey() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"bC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":3}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $requests of doAuthenticate() method only accepts array of object.
   */
  public function testDoAuthenticateInvalidRequests2() {
    $reqs = array('YubiKey NEO', 'YubiKey Standard');
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":3}'));
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $registrations of doAuthenticate() method only accepts array of object.
   */
  public function testDoAuthenticateInvalidRegistrations2() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array('YubiKey NEO', 'YubiKey Standard');
    $resp = json_decode('{ "signatureData": "AQAAAAQwRQIhAI6FSrMD3KUUtkpiP0jpIEakql-HNhwWFngyw553pS1CAiAKLjACPOhxzZXuZsVO8im-HStEcYGC50PKhsGp_SUAng==", "clientData": "eyAiY2hhbGxlbmdlIjogImZFbmM5b1Y3OUVhQmdLNUJvTkVSVTVnUEtNMlhHWVdyejRmVWpnYzBRN2ciLCAib3JpZ2luIjogImh0dHA6XC9cL2RlbW8uZXhhbXBsZS5jb20iLCAidHlwIjogIm5hdmlnYXRvci5pZC5nZXRBc3NlcnRpb24iIH0=", "keyHandle": "CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w" }');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $response of doAuthenticate() method only accepts object.
   */
  public function testDoAuthenticateInvalidResponse() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":3}'));
    $resp = 'Response';
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }

  /**
   * @expectedException u2flib_server\Error
   * @expectedExceptionCode u2flib_server\ERR_BAD_UA_RETURNING
   */
  public function testDoAuthenticateUAError() {
    $reqs = array(json_decode('{"version":"U2F_V2","challenge":"fEnc9oV79EaBgK5BoNERU5gPKM2XGYWrz4fUjgc0Q7g","keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","appId":"http://demo.example.com"}'));
    $regs = array(json_decode('{"keyHandle":"CTUayZo8hCBeC-sGQJChC0wW-bBg99bmOlGCgw8XGq4dLsxO3yWh9mRYArZxocP5hBB1pEGB3bbJYiM-5acc5w","publicKey":"BC0SaFZWC9uH7wamOwduP93kUH2I2hEvyY0Srfj4A258pZSlV0iPoFIH+bd4yhncaqdoPLdEDl5Y\/yaFORPUe3c=","certificate":"MIIC4jCBywIBATANBgkqhkiG9w0BAQsFADAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgQ0EwHhcNMTQwNTE1MTI1ODU0WhcNMTQwNjE0MTI1ODU0WjAdMRswGQYDVQQDExJZdWJpY28gVTJGIFRlc3QgRUUwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATbCtv1IcdczmPcpuHoJQYNlOYnVBlPnSSvJhq+rZlEH5WjcZEKOiDnPpFeE+i+OAV61XqjfnaQj6\/iipS2MOudMA0GCSqGSIb3DQEBCwUAA4ICAQCVQGtQYX2thKO064gP4zAPLaIKANklBO5y+mffWFEPC0cCnD5BKUqTrCmFiS2keoEyKFdxAe+oQogWljeR1d\/gj8k8jbDNiXCC7HnTxnhzKTLlq2y9Vp\/VRZHOwd2NZNzpnB9ePNKvUaWCGK\/gN+cynnYFdwJ75iSgMVYb\/RnFcdPwnsBzBU68hbhTnu\/FvJxWo7rZJ2q7qXpA10eLVXJr4\/4oSXEk9I\/0IIHqOP98Ck\/fAoI5gYI7ygndyqoPJ\/Wkg1VsmjmbFToWY9xb+axbvPefvg+KojwxE6MySMpYh\/h7oKEKamCWk19dJp5jHQmumkHlvQhH\/uUJmyD9EuLmQH+6SmEzZg0Oc9uw1aKamhcNNDCFakJGnv80j1+HbDXnqE0168FBqorS2hmqeaJfNSyg\/SXT950lGC36tLy7BzQ8jYG99Ok32znp0UVbIEEvLSci3JJ0ipLVg\/0J+xOb4zl6a1z65nae4OTj7628\/UJFmtSU0X6Np9gF1dNizxXPlH0fW1ggRCCQcb5m6ZqrdDJwUx1p7Ydm9AlPyiUwwmN5ADyxmzk\/AOCoiO96UVvnvUlk2kF7JMNxIv3R0SCzP5fTl7KqGByeA3d7W375o6DWIIEsOI+dJd7pyPXdakecZQRaVubC6\/ICl+G52OEkdp8jYjkDS8j3NAdJ1udNmg==", "counter":3}'));
    $resp = json_decode('{"errorCode": "5"}');
    $this->u2f->doAuthenticate($reqs, $regs, $resp);
  }
}

?>
