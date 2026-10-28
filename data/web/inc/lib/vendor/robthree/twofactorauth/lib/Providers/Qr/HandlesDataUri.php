<?php

namespace RobThree\Auth\Providers\Qr;

trait HandlesDataUri
{
	/**
     * @param string $datauri
     *
     * @return null|array
     */
    private function DecodeDataUri($datauri)
    {
        if (preg_match('/data:(?P<mimetype>[\w\.\-\+\/]+);(?P<encoding>\w+),(?P<data>.*)/', $datauri, $m) === 1) {
            return array(
                'mimetype' => $m['mimetype'],
                'encoding' => $m['encoding'],
                'data' => base64_decode($m['data'])
            );
        }

        return null;
    }
}
