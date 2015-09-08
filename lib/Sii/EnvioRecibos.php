<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General GNU para obtener
 * una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/gpl.html>.
 */

namespace sasco\LibreDTE\Sii;

/**
 * Clase que representa el envío de un recibo por entrega de mercadería o
 * servicios prestados por un proveedor
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2015-09-08
 */
class EnvioRecibos
{

    private $recibos = []; ///< recibos que se adjuntarán
    private $caratula; ///< arreglo con la caratula del envío
    private $Firma; ///< objeto de la firma electrónica
    private $xml_data; ///< String con el documento XML

    /**
     * Método que agrega un recibo
     * @param datos Arreglo con los datos del recibo
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function agregar(array $datos)
    {
        $this->recibos[] = [
            '@attributes' => [
                'version' => '1.0',
            ],
            'DocumentoRecibo' => array_merge([
                '@attributes' => [
                    'ID' => 'T'.$datos['TipoDoc'].'F'.$datos['Folio'],
                ],
                'TipoDoc' => false,
                'Folio' => false,
                'FchEmis' => false,
                'RUTEmisor' => false,
                'RUTRecep' => false,
                'MntTotal' => false,
                'Recinto' => false,
                'RutFirma' => false,
                'Declaracion' => 'El acuse de recibo que se declara en este acto, de acuerdo a lo dispuesto en la letra b) del Art. 4, y la letra c) del Art. 5 de la Ley 19.983, acredita que la entrega de mercaderias o servicio(s) prestado(s) ha(n) sido recibido(s).',
                'TmstFirmaRecibo' => date('Y-m-d\TH:i:s'),
            ], $datos)
        ];
        return true;
    }

    /**
     * Método para asignar la caratula
     * @param caratula Arreglo con datos del envío: RutEnvia, FchResol y NroResol
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function setCaratula(array $caratula)
    {
        $this->caratula = array_merge([
            '@attributes' => [
                'version' => '1.0'
            ],
            'RutResponde' => false,
            'RutRecibe' => false,
            'NmbContacto' => false,
            'FonoContacto' => false,
            'MailContacto' => false,
            'TmstFirmaEnv' => date('Y-m-d\TH:i:s'),
        ], $caratula);
    }

    /**
     * Método para asignar la caratula
     * @param Firma Objeto con la firma electrónica
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-07
     */
    public function setFirma(\sasco\LibreDTE\FirmaElectronica $Firma)
    {
        $this->Firma = $Firma;
    }

    /**
     * Método que genera el XML para el envío de la respuesta al SII
     * @param caratula Arreglo con la carátula de la respuesta
     * @param Firma Objeto con la firma electrónica
     * @return XML con la respuesta firmada o =false si no se pudo generar o firmar la respuesta
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function generar()
    {
        // si ya se había generado se entrega directamente
        if ($this->xml_data)
            return $this->xml_data;
        // si no hay respuestas para generar entregar falso
        if (!isset($this->recibos[0]))
            return false;
        // si no hay carátula error
        if (!$this->caratula)
            return false;
        // crear arreglo de lo que se enviará
        $xmlEnvio = (new \sasco\LibreDTE\XML())->generate([
            'EnvioRecibos' => [
                '@attributes' => [
                    'xmlns' => 'http://www.sii.cl/SiiDte',
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xsi:schemaLocation' => 'http://www.sii.cl/SiiDte EnvioRecibo_v10.xsd',
                    'version' => '1.0',
                ],
                'SetRecibos' => [
                    '@attributes' => [
                        'ID' => 'SetDteRecibidos'
                    ],
                    'Caratula' => $this->caratula,
                    'Recibo' => null,
                ]
            ]
        ])->saveXML();
        // generar cada recibo y firmar
        $Recibos = [];
        foreach ($this->recibos as &$recibo) {
            $recibo_xml = new \sasco\LibreDTE\XML();
            $recibo_xml->generate(['Recibo'=>$recibo]);
            $recibo_firmado = $this->Firma ? $this->Firma->signXML($recibo_xml->saveXML(), '#'.$recibo['DocumentoRecibo']['@attributes']['ID'], 'DocumentoRecibo', true) : $recibo_xml->saveXML();
            $Recibos[] = trim(str_replace('<?xml version="1.0" encoding="ISO-8859-1"?>', '', $recibo_firmado));
        }
        // firmar XML del envío y entregar
        $xml = str_replace('<Recibo/>', implode("\n", $Recibos), $xmlEnvio);
        $this->xml_data = $this->Firma ? $this->Firma->signXML($xml, '#SetDteRecibidos', 'SetRecibos', true) : $xml;
        return $this->xml_data;
    }

    /**
     * Método que valida el XML que se genera para la respuesta del envío
     * @return =true si el schema del documento del envío es válido, =null si no se pudo determinar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-08
     */
    public function schemaValidate()
    {
        if (!$this->xml_data)
            return null;
        $xsd = dirname(dirname(dirname(__FILE__))).'/schemas/EnvioRecibos_v10.xsd';
        $this->xml = new \sasco\LibreDTE\XML();
        $this->xml->loadXML($this->xml_data);
        return $this->xml->schemaValidate($xsd);
    }

}
