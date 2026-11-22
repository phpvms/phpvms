<?php

declare(strict_types=1);

/**
 * Renders a SimBrief v2 JSON OFP as the 7.x XML SimBrief serves: lists as repeated elements,
 * the wrapped lists inside their wrapper, booleans as 1/0, and plan_html as CDATA.
 *
 * @param array<string, mixed> $ofp
 */
function simbriefOfpXml(array $ofp): string
{
    unset($ofp['fetch']);

    return '<?xml version="1.0" encoding="UTF-8"?>'."\n".simbriefXmlElement('OFP', $ofp, '');
}

function simbriefXmlElement(string $name, mixed $value, string $path): string
{
    $wrapped = [
        'navlog'                            => 'fix',
        'navlog.*.wind_data'                => 'level',
        'navlog.*.fir_crossing'             => 'fir',
        'alternate_navlog.*'                => 'fix',
        'alternate_navlog.*.*.wind_data'    => 'level',
        'alternate_navlog.*.*.fir_crossing' => 'fir',
        'notams'                            => 'notamdrec',
        'sigmets'                           => 'sigmet',
        'tracks.nat.*.fixes'                => 'fix',
    ];
    $generic = preg_replace('/\.\d+(?=\.|$)/', '.*', $path);

    if (is_array($value) && array_is_list($value)) {
        if (isset($wrapped[$generic])) {
            $inner = '';
            foreach ($value as $i => $item) {
                $inner .= simbriefXmlElement($wrapped[$generic], $item, $path.'.'.$i);
            }

            return '<'.$name.'>'.$inner.'</'.$name.'>';
        }

        if ($value === []) {
            return '<'.$name.'/>';
        }

        $out = '';
        foreach ($value as $i => $item) {
            $out .= simbriefXmlElement($name, $item, $path.'.'.$i);
        }

        return $out;
    }

    if (is_array($value)) {
        $inner = '';
        foreach ($value as $key => $item) {
            $inner .= simbriefXmlElement((string) $key, $item, ltrim($path.'.'.$key, '.'));
        }

        return '<'.$name.'>'.$inner.'</'.$name.'>';
    }

    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }

    if ($path === 'text.plan_html') {
        return '<'.$name.'><![CDATA['.$value.']]></'.$name.'>';
    }

    return '<'.$name.'>'.htmlspecialchars((string) $value, ENT_XML1).'</'.$name.'>';
}
