<?php

use MatthiasMullie\Minify\CSS;

class CSSminifierExtended extends CSS {

    public function getDataHash() {
        return sha1(json_encode($this->normalizeData($this->accessProtected($this, 'data'))));
    }

    private function accessProtected($obj, $prop) {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    private function normalizeData($data) {
        if (is_array($data)) {
            return array_map([$this, 'normalizeData'], $data);
        }

        if (is_string($data) && is_file($data)) {
            return [
                'path' => $data,
                'sha1' => sha1_file($data),
            ];
        }

        return $data;
    }

}
