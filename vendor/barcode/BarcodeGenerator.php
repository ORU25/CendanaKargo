<?php

namespace Barcode;

class BarcodeGenerator
{
    private $data;
    private $type;
    private $height;
    private $width;
    
    const TYPE_CODE_128 = 'code128';
    const TYPE_CODE_39 = 'code39';
    
    public function __construct($type = self::TYPE_CODE_128)
    {
        $this->type = $type;
        $this->height = 50;
        $this->width = 2;
    }
    
    public function setHeight($height)
    {
        $this->height = $height;
        return $this;
    }
    
    public function setWidth($width)
    {
        $this->width = $width;
        return $this;
    }
    
    public function generate($data)
    {
        $this->data = $data;
        
        if ($this->type === self::TYPE_CODE_128) {
            return $this->generateCode128();
        }
        
        return $this->generateCode128();
    }
    
    private function generateCode128()
    {
        $code = $this->data;
        
        // Code 128 character set
        $code_array = [
            ' ', '!', '"', '#', '$', '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?',
            '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_',
            '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o',
            'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~'
        ];
        
        // Code 128B patterns
        $code_patterns = [
            '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
            '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
            '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
            '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
            '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
            '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
            '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
            '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
            '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
            '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
            '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
            '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
            '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
            '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
            '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
            '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
            '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
            '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
            '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
            '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
            '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
            '11010011100', '1100011101011'
        ];
        
        $sum = 104; // Start Code B
        $checksum = 104;
        
        // Calculate checksum
        for ($i = 0; $i < strlen($code); $i++) {
            $char = substr($code, $i, 1);
            $index = array_search($char, $code_array);
            if ($index !== false) {
                $checksum += ($i + 1) * $index;
            }
        }
        $checksum = $checksum % 103;
        
        // Build barcode
        $barcode = '11010010000'; // Start Code B
        
        for ($i = 0; $i < strlen($code); $i++) {
            $char = substr($code, $i, 1);
            $index = array_search($char, $code_array);
            if ($index !== false && isset($code_patterns[$index])) {
                $barcode .= $code_patterns[$index];
            }
        }
        
        // Add checksum
        if (isset($code_patterns[$checksum])) {
            $barcode .= $code_patterns[$checksum];
        }
        
        // Stop pattern
        $barcode .= '1100011101011';
        
        return $barcode;
    }
    
    public function getBarcodePNG($data, $width = 2, $height = 50)
    {
        $barcode_string = $this->generate($data);
        
        $img_width = strlen($barcode_string) * $width;
        $img_height = $height;
        
        $image = imagecreate($img_width, $img_height + 20);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        // Draw barcode
        for ($i = 0; $i < strlen($barcode_string); $i++) {
            if ($barcode_string[$i] == '1') {
                imagefilledrectangle($image, $i * $width, 0, ($i + 1) * $width - 1, $img_height, $black);
            }
        }
        
        // Add text below barcode
        $font_size = 3;
        $text_width = imagefontwidth($font_size) * strlen($data);
        $text_x = ($img_width - $text_width) / 2;
        $text_y = $img_height + 2;
        
        imagestring($image, $font_size, $text_x, $text_y, $data, $black);
        
        ob_start();
        imagepng($image);
        $image_data = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return $image_data;
    }
    
    public function getBarcodeSVG($data)
    {
        $barcode_string = $this->generate($data);
        
        $width = $this->width;
        $height = $this->height;
        
        $svg_width = strlen($barcode_string) * $width;
        $svg_height = $height + 20;
        
        $svg = '<svg width="' . $svg_width . '" height="' . $svg_height . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="' . $svg_width . '" height="' . $svg_height . '" fill="white"/>';
        
        for ($i = 0; $i < strlen($barcode_string); $i++) {
            if ($barcode_string[$i] == '1') {
                $x = $i * $width;
                $svg .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '" fill="black"/>';
            }
        }
        
        // Add text
        $text_x = $svg_width / 2;
        $text_y = $height + 15;
        $svg .= '<text x="' . $text_x . '" y="' . $text_y . '" font-family="monospace" font-size="18" text-anchor="middle" fill="#8B0000">' . htmlspecialchars($data) . '</text>';
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    public function getBarcodeHTML($data, $width = 2, $height = 50)
    {
        $barcode_string = $this->generate($data);
        
        $html = '<div style="font-family: monospace; display: inline-block; background: white; padding: 10px;">';
        $html .= '<div style="display: flex; align-items: flex-end;">';
        
        for ($i = 0; $i < strlen($barcode_string); $i++) {
            $color = ($barcode_string[$i] == '1') ? 'black' : 'white';
            $html .= '<div style="width: ' . $width . 'px; height: ' . $height . 'px; background-color: ' . $color . ';"></div>';
        }
        
        $html .= '</div>';
        $html .= '<div style="text-align: center; margin-top: 5px; font-size: 14px;">' . htmlspecialchars($data) . '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
