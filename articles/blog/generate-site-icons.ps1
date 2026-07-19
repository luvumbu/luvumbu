Add-Type -AssemblyName System.Drawing

function New-LeafIcon {
    param([int]$Size, [string]$Path)

    $bmp = New-Object System.Drawing.Bitmap $Size, $Size
    $g   = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode     = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode   = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

    # Fond degrade vert
    $rectBg = New-Object System.Drawing.Rectangle 0, 0, $Size, $Size
    $brushBg = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        $rectBg,
        [System.Drawing.Color]::FromArgb(255,27,94,32),
        [System.Drawing.Color]::FromArgb(255,102,187,106),
        135.0
    )
    $g.FillRectangle($brushBg, $rectBg)
    $brushBg.Dispose()

    # Forme feuille : teardrop pointu en haut, arrondi en bas
    $state = $g.Save()
    $g.TranslateTransform([float]($Size/2.0), [float]($Size/2.0))
    $g.RotateTransform(-25.0)
    $g.TranslateTransform([float](-$Size/2.0), [float](-$Size/2.0))

    $cx = $Size / 2.0
    $cy = $Size / 2.0
    $leafW = $Size * 0.38
    $leafH = $Size * 0.88
    $topY = $cy - $leafH/2.0
    $botY = $cy + $leafH/2.0

    $leafPath = New-Object System.Drawing.Drawing2D.GraphicsPath
    $top    = New-Object System.Drawing.PointF ([float]$cx, [float]$topY)
    $bottom = New-Object System.Drawing.PointF ([float]$cx, [float]$botY)

    $cRight1 = New-Object System.Drawing.PointF ([float]($cx + $leafW*1.10), [float]($topY + $leafH*0.18))
    $cRight2 = New-Object System.Drawing.PointF ([float]($cx + $leafW*1.10), [float]($botY - $leafH*0.18))
    $leafPath.AddBezier($top, $cRight1, $cRight2, $bottom)

    $cLeft1 = New-Object System.Drawing.PointF ([float]($cx - $leafW*1.10), [float]($botY - $leafH*0.18))
    $cLeft2 = New-Object System.Drawing.PointF ([float]($cx - $leafW*1.10), [float]($topY + $leafH*0.18))
    $leafPath.AddBezier($bottom, $cLeft1, $cLeft2, $top)
    $leafPath.CloseFigure()

    # Remplissage feuille avec gradient blanc/vert
    $rectLeaf = New-Object System.Drawing.RectangleF (
        [float]($cx - $leafW), [float]$topY, [float]($leafW * 2), [float]$leafH
    )
    $brushLeaf = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        $rectLeaf,
        [System.Drawing.Color]::FromArgb(255,241,248,233),
        [System.Drawing.Color]::FromArgb(255,156,204,101),
        135.0
    )
    $g.FillPath($brushLeaf, $leafPath)
    $brushLeaf.Dispose()

    # Contour de la feuille
    $penLeaf = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255,27,94,32), [float]($Size * 0.025))
    $penLeaf.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $g.DrawPath($penLeaf, $leafPath)
    $penLeaf.Dispose()

    # Nervure centrale
    $penVein = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255,46,125,50), [float]($Size * 0.022))
    $penVein.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $penVein.EndCap   = [System.Drawing.Drawing2D.LineCap]::Round
    $g.DrawLine($penVein, $top.X, $top.Y, $bottom.X, $bottom.Y)
    $penVein.Dispose()

    # Petites nervures laterales
    $penSide = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255,67,160,71), [float]($Size * 0.013))
    $penSide.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $penSide.EndCap   = [System.Drawing.Drawing2D.LineCap]::Round
    for ($i = 1; $i -le 5; $i++) {
        $t = $i / 6.0
        $y = $topY + ($botY - $topY) * $t
        $halfWidthAt = $leafW * 0.78 * [Math]::Sin([Math]::PI * $t)
        $w = $halfWidthAt * 0.70
        $dy = $w * 0.55
        $g.DrawLine($penSide, [float]$cx, [float]$y, [float]($cx - $w), [float]($y + $dy))
        $g.DrawLine($penSide, [float]$cx, [float]$y, [float]($cx + $w), [float]($y + $dy))
    }
    $penSide.Dispose()

    # Petite tige sous la feuille
    $penStem = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255,93,64,55), [float]($Size * 0.028))
    $penStem.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $penStem.EndCap   = [System.Drawing.Drawing2D.LineCap]::Round
    $g.DrawLine($penStem, [float]$cx, [float]$botY, [float]$cx, [float]($botY + $Size * 0.08))
    $penStem.Dispose()

    $g.Restore($state)

    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose()
    $bmp.Dispose()
    Write-Output "Genere : $Path ($Size x $Size)"
}

$outDir = "C:\xampp\htdocs\Blog"
New-LeafIcon -Size 192 -Path "$outDir\icon-192.png"
New-LeafIcon -Size 512 -Path "$outDir\icon-512.png"
Write-Output "Termine."
