<?php
/**
 * Normalize PDF pages to specific size with proper centering
 */
function normalizePdfPage($inputFile, $outputFile, $pageWidth, $pageHeight) {
    $gsPath = '/opt/homebrew/bin/gs';
    if (!file_exists($gsPath)) {
        $gsPath = 'gs';
    }
    
    // Use ImageMagick for better centering control
    $magickPath = '/opt/homebrew/bin/magick';
    if (!file_exists($magickPath)) {
        $magickPath = 'magick';
    }
    
    // Try ImageMagick first as it handles centering better
    $imagickCommand = sprintf(
        'MAGICK_TMPDIR=%s %s convert -density 150 %s -gravity center -background white -extent %dx%d -compress jpeg -quality 90 %s 2>&1',
        escapeshellarg(TEMP_DIR),
        $magickPath,
        escapeshellarg($inputFile),
        $pageWidth,
        $pageHeight,
        escapeshellarg($outputFile)
    );
    
    exec($imagickCommand, $imagickOutput, $imagickReturn);
    
    if ($imagickReturn === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
        return true;
    }
    
    // Fallback to Ghostscript with proper centering
    // Create a PostScript wrapper for centering
    $psWrapper = dirname($outputFile) . '/wrapper_' . uniqid() . '.ps';
    $psContent = sprintf('%%!PS
%%%%BoundingBox: 0 0 %d %d
<< /PageSize [%d %d] >> setpagedevice

/centerpage {
  gsave
  newpath clippath pathbbox
  /ury exch def /urx exch def /lly exch def /llx exch def
  
  /pagewidth %d def
  /pageheight %d def
  /contentwidth urx llx sub def
  /contentheight ury lly sub def
  
  %% Calculate scale
  pagewidth contentwidth div
  pageheight contentheight div
  2 copy gt {exch} if pop
  0.9 mul /scale exch def
  
  %% Calculate centering offset
  pagewidth contentwidth scale mul sub 2 div
  pageheight contentheight scale mul sub 2 div
  translate
  
  scale scale scale
  llx neg lly neg translate
  grestore
} def

<<
  /EndPage {
    exch pop
    0 eq {
      centerpage
      true
    } {
      false
    } ifelse
  }
>> setpagedevice

(%s) (r) file run
',
        $pageWidth, $pageHeight,
        $pageWidth, $pageHeight,
        $pageWidth, $pageHeight,
        $inputFile
    );
    
    file_put_contents($psWrapper, $psContent);
    
    $gsCommand = sprintf(
        'TMPDIR=%s %s -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite ' .
        '-dDEVICEWIDTHPOINTS=%d -dDEVICEHEIGHTPOINTS=%d ' .
        '-sOutputFile=%s %s 2>&1',
        escapeshellarg(TEMP_DIR),
        $gsPath,
        $pageWidth,
        $pageHeight,
        escapeshellarg($outputFile),
        escapeshellarg($psWrapper)
    );
    
    exec($gsCommand, $gsOutput, $gsReturn);
    
    // Clean up
    if (file_exists($psWrapper)) {
        unlink($psWrapper);
    }
    
    // If PostScript fails, use simple Ghostscript
    if ($gsReturn !== 0 || !file_exists($outputFile)) {
        $simpleCommand = sprintf(
            'TMPDIR=%s %s -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite ' .
            '-dDEVICEWIDTHPOINTS=%d -dDEVICEHEIGHTPOINTS=%d ' .
            '-dFIXEDMEDIA -dPDFFitPage -dAutoRotatePages=/None ' .
            '-sOutputFile=%s %s 2>&1',
            escapeshellarg(TEMP_DIR),
            $gsPath,
            $pageWidth,
            $pageHeight,
            escapeshellarg($outputFile),
            escapeshellarg($inputFile)
        );
        
        exec($simpleCommand, $output, $returnCode);
        return $returnCode === 0 && file_exists($outputFile);
    }
    
    return true;
}