<?php
//
//  FPDF_TPL - Version 1.2.3
//
//    Copyright 2004-2013 Setasign - Jan Slabon
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

class FPDF_TPL extends FPDF {
    /**
     * Array of Tpl-Data
     * @var array
     */
    var $tpls = array();

    /**
     * Current Template-ID
     * @var int
     */
    var $tpl = 0;
    
    /**
     * "In Template"-Flag
     * @var boolean
     */
    var $_intpl = false;
    
    /**
     * Nameprefix of Templates used in Resources-Dictonary
     * @var string A String defining the Prefix used as Template-Object-Names. Have to beginn with an /
     */
    var $tplprefix = "/TPL";

    /**
     * Resources used By Templates and Pages
     * @var array
     */
    var $_res = array();
    
    /**
     * Last used Template data
     *
     * @var array
     */
    var $lastUsedTemplateData = array();
    
    /**
     * Use a Template in current Page or other Template
     *
     * You can use a template in a page or in another template.
     * You can give the used template a new size like you use the Image()-method.
     * All parameters are optional. The width or height is calculated automaticaly
     * if one is given. If no parameter is given the origin size as defined in
     * beginTemplate() is used.
     * The calculated or used width and height are returned as an array.
     *
     * @param int $tplidx A valid template-Id
     * @param int $_x The x-position
     * @param int $_y The y-position
     * @param int $_w The new width of the template
     * @param int $_h The new height of the template
     * @retrun array The height and width of the template
     */
    function useTemplate($tplidx, $_x = null, $_y = null, $_w = 0, $_h = 0) {
        if ($this->page <= 0)
            $this->error('You have to add a page first!');
        
        if (!isset($this->tpls[$tplidx]))
            $this->error('Template does not exist!');
            
        if ($this->_intpl) {
            $this->_res['tpl'][$this->tpl]['tpls'][$tplidx] =& $this->tpls[$tplidx];
        }
        
        $tpl =& $this->tpls[$tplidx];
        $w = $tpl['w'];
        $h = $tpl['h'];
        
        if ($_x == null)
            $_x = 0;
        if ($_y == null)
            $_y = 0;
            
        $_x += $tpl['x'];
        $_y += $tpl['y'];
        
        $wh = $this->getTemplateSize($tplidx, $_w, $_h);
        $_w = $wh['w'];
        $_h = $wh['h'];
        
        $tData = array(
            'x' => $this->x,
            'y' => $this->y,
            'w' => $_w,
            'h' => $_h,
            'scaleX' => ($_w / $w),
            'scaleY' => ($_h / $h),
            'tx' => $_x,
            'ty' =>  ($this->h - $_y - $_h),
            'lty' => ($this->h - $_y - $_h) - ($this->h - $h) * ($_h / $h)
        );
        
        $this->_out(sprintf('q %.4F 0 0 %.4F %.4F %.4F cm', $tData['scaleX'], $tData['scaleY'], $tData['tx'] * $this->k, $tData['ty'] * $this->k)); // Translate 
        $this->_out(sprintf('%s%d Do Q', $this->tplprefix, $tplidx));

        $this->lastUsedTemplateData = $tData;
        
        return array('w' => $_w, 'h' => $_h);
    }
    
    /**
     * Get The calculated Size of a Template
     *
     * If one size is given, this method calculates the other one.
     *
     * @param int $tplidx A valid template-Id
     * @param int $_w The width of the template
     * @param int $_h The height of the template
     * @return array The height and width of the template
     */
    function getTemplateSize($tplidx, $_w = 0, $_h = 0) {
        if (!isset($this->tpls[$tplidx]))
            return false;

        $tpl =& $this->tpls[$tplidx];
        $w = $tpl['w'];
        $h = $tpl['h'];
        
        if ($_w == 0 and $_h == 0) {
            $_w = $w;
            $_h = $h;
        }

        if($_w == 0)
            $_w = $_h * $w / $h;
        if($_h == 0)
            $_h = $_w * $h / $w;
            
        return array("w" => $_w, "h" => $_h);
    }
    
    /**
     * Private Method that writes the form xobjects
     */
    public function _putformxobjects() {
        $filter=($this->compress) ? '/Filter /FlateDecode ' : '';
        reset($this->tpls);
        foreach($this->tpls AS $tplidx => $tpl) {

            $p=($this->compress) ? gzcompress($tpl['buffer']) : $tpl['buffer'];
            $this->_newobj();
            $this->tpls[$tplidx]['n'] = $this->n;
            $this->_out('<<'.$filter.'/Type /XObject');
            $this->_out('/Subtype /Form');
            $this->_out('/FormType 1');
            $this->_out(sprintf('/BBox [%.2F %.2F %.2F %.2F]',
                // llx
                $tpl['x'] * $this->k,
                // lly
                -$tpl['y'] * $this->k,
                // urx
                ($tpl['w'] + $tpl['x']) * $this->k,
                // ury
                ($tpl['h'] - $tpl['y']) * $this->k
            ));
            
            if ($tpl['x'] != 0 || $tpl['y'] != 0) {
                $this->_out(sprintf('/Matrix [1 0 0 1 %.5F %.5F]',
                     -$tpl['x'] * $this->k * 2, $tpl['y'] * $this->k * 2
                ));
            }
            
            $this->_out('/Resources ');

            $this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
            if (isset($this->_res['tpl'][$tplidx]['fonts']) && count($this->_res['tpl'][$tplidx]['fonts'])) {
                $this->_out('/Font <<');
                foreach($this->_res['tpl'][$tplidx]['fonts'] as $font)
                    $this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
                $this->_out('>>');
            }
            if(isset($this->_res['tpl'][$tplidx]['images']) && count($this->_res['tpl'][$tplidx]['images']) || 
               isset($this->_res['tpl'][$tplidx]['tpls']) && count($this->_res['tpl'][$tplidx]['tpls']))
            {
                $this->_out('/XObject <<');
                if (isset($this->_res['tpl'][$tplidx]['images']) && count($this->_res['tpl'][$tplidx]['images'])) {
                    foreach($this->_res['tpl'][$tplidx]['images'] as $image)
                          $this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
                }
                if (isset($this->_res['tpl'][$tplidx]['tpls']) && count($this->_res['tpl'][$tplidx]['tpls'])) {
                    foreach($this->_res['tpl'][$tplidx]['tpls'] as $i => $tpl)
                        $this->_out($this->tplprefix . $i . ' ' . $tpl['n'] . ' 0 R');
                }
                $this->_out('>>');
            }
            $this->_out('>>');
            
            $this->_out('/Length ' . strlen($p) . ' >>');
            $this->_putstream($p);
            $this->_out('endobj');
        }
    }
    
    /**
     * Overwritten to add _putformxobjects() after _putimages()
     *
     */
    function _putimages() {
        parent::_putimages();
        $this->_putformxobjects();
    }
    
    function _putxobjectdict() {
        parent::_putxobjectdict();
        
        if (count($this->tpls)) {
            foreach($this->tpls as $tplidx => $tpl) {
                $this->_out(sprintf('%s%d %d 0 R', $this->tplprefix, $tplidx, $tpl['n']));
            }
        }
    }

    /**
     * Private Method
     */
    function _out($s) {
        if ($this->state == 2 && $this->_intpl) {
            $this->tpls[$this->tpl]['buffer'] .= $s . "\n";
        } else {
            parent::_out($s);
        }
    }
}
