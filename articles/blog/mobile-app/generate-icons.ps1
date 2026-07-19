Add-Type -AssemblyName System.Drawing

function New-LeafIcon {
    param([int]$Size, [string]$Path)

    $bmp = New-Object System.Drawing.Bitmap $Size, $Size
    $g   = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode     = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode   = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

    # ---- Fond degrade vert ----
    $rectBg  = New-Object System.Drawing.Rectangle 0, 0, $Size, $Size
    $brushBg = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        $rectBg,
        [System.Drawing.Color]::FromArgb(255,27,94,32),
        [System.Drawing.Color]::FromArgb(255,67,160,71),
        135.0
    )
    $g.FillRectangle($brushBg, $rectBg)
    $brushBg.Dispose()

    $cx = $Size / 2.0
    $cy = $Size / 2.0

    # ---- La feuille est dessinee inclinee (diagonale) ----
    $state = $g.Save()
    $g.TranslateTransform([float]$cx, [float]$cy)
    $g.RotateTransform(-20.0)
    $g.TranslateTransform([float](-$cx), [float](-$cy))

    $ty   = $Size * 0.15           # pointe haute
    $by   = $Size * 0.85           # pointe basse
    $span = $by - $ty
    $lw   = $Size * 0.27           # demi-largeur (bombe des cotes)

    $T = New-Object System.Drawing.PointF([float]$cx, [float]$ty)
    $B = New-Object System.Drawing.PointF([float]$cx, [float]$by)

    $leaf = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf.AddBezier(
        $T,
        (New-Object System.Drawing.PointF([float]($cx - $lw), [float]($ty + $span * 0.12))),
        (New-Object System.Drawing.PointF([float]($cx - $lw), [float]($by - $span * 0.12))),
        $B
    )
    $leaf.AddBezier(
        $B,
        (New-Object System.Drawing.PointF([float]($cx + $lw), [float]($by - $span * 0.12))),
        (New-Object System.Drawing.PointF([float]($cx + $lw), [float]($ty + $span * 0.12))),
        $T
    )
    $leaf.CloseFigure()

    # ---- Remplissage de la feuille (degrade clair -> vert) ----
    $boundsRect = New-Object System.Drawing.RectangleF(
        [float]($cx - $lw), [float]$ty, [float]($lw * 2), [float]$span
    )
    $brushLeaf = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        $boundsRect,
        [System.Drawing.Color]::FromArgb(255,237,247,237),
        [System.Drawing.Color]::FromArgb(255,129,199,132),
        90.0
    )
    $g.FillPath($brushLeaf, $leaf)
    $brushLeaf.Dispose()

    # ---- Contour de la feuille ----
    $penLeaf = New-Object System.Drawing.Pen(
        [System.Drawing.Color]::FromArgb(255,27,94,32),
        [float]($Size * 0.022)
    )
    $penLeaf.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $g.DrawPath($penLeaf, $leaf)

    # ---- Nervure centrale ----
    $g.DrawLine($penLeaf,
        [float]$cx, [float]($by - $span * 0.04),
        [float]$cx, [float]($ty + $span * 0.06)
    )

    # ---- Nervures laterales ----
    $penVein = New-Object System.Drawing.Pen(
        [System.Drawing.Color]::FromArgb(255,46,125,50),
        [float]($Size * 0.014)
    )
    $penVein.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    foreach ($lv in @(0.32, 0.50, 0.68)) {
        $yy = $ty + $span * $lv
        $g.DrawLine($penVein, [float]$cx, [float]$yy, [float]($cx - $lw * 0.6), [float]($yy + $span * 0.10))
        $g.DrawLine($penVein, [float]$cx, [float]$yy, [float]($cx + $lw * 0.6), [float]($yy + $span * 0.10))
    }
    $penVein.Dispose()
    $penLeaf.Dispose()
    $leaf.Dispose()

    $g.Restore($state)

    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose()
    $bmp.Dispose()
    Write-Output "Genere : $Path ($Size x $Size)"
}

$outDir = "C:\xampp\htdocs\Blog\blog\mobile-app"
New-LeafIcon -Size 192 -Path "$outDir\icon-192.png"
New-LeafIcon -Size 512 -Path "$outDir\icon-512.png"
Write-Output "Termine."
