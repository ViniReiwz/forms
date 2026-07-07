<?php

namespace Uspdev\Forms\Replicado;

use Uspdev\Replicado\Bempatrimoniado as BempatrimoniadoReplicado;

class Bempatrimoniado extends BempatrimoniadoReplicado
{
    public static function listarPatrimoniosAjax($numero)
    {
        $results = [];

        if ($numero) {
            $numpat = str_replace('.', '', $numero);
            $patrimonio = BempatrimoniadoReplicado::dump($numpat);

            if (!empty($patrimonio)) {
                $text = $numpat;
                if (isset($patrimonio['epfmarpat'], $patrimonio['tippat'], $patrimonio['modpat'])) {
                    $text .= ' - ' . $patrimonio['epfmarpat'] . ' - ' . $patrimonio['tippat'] . ' - ' . $patrimonio['modpat'];
                }

                $results[] = [
                    'text' => $text,
                    'id' => $numpat,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

}
