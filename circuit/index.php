<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Arduino - Bouton + LED</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #e5e7eb; line-height: 1.6; }
    .container { max-width: 900px; margin: 0 auto; padding: 30px 20px; }
    h1 { font-size: 2em; margin-bottom: 10px; }
    h2 { color: #60a5fa; margin: 30px 0 15px; font-size: 1.4em; border-bottom: 1px solid #1e3a5f; padding-bottom: 8px; }
    h3 { color: #93c5fd; margin: 15px 0 10px; font-size: 1.1em; }
    ul { margin-left: 25px; margin-bottom: 15px; }
    li { margin-bottom: 5px; }
    .section { background: #1e293b; border-radius: 10px; padding: 20px 25px; margin-bottom: 20px; }
    .badge { display: inline-block; background: #1e3a8a; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; margin-right: 8px; }

    /* Bouton grid */
    .btn-grid { display: inline-grid; grid-template-columns: 50px 50px; gap: 5px; background: #374151; padding: 15px; border-radius: 8px; margin: 10px 0; }
    .btn-cell { width: 50px; height: 40px; display: flex; align-items: center; justify-content: center; background: #4b5563; border-radius: 4px; font-weight: bold; color: #facc15; font-size: 1.2em; }

    /* Connexions internes */
    .conn { background: #1e3a5f; padding: 10px 15px; border-radius: 6px; margin: 10px 0; font-family: monospace; }

    /* Schema SVG */
    .board { margin: 20px 0; }
    svg { width: 100%; height: auto; }
    .label { font-size: 14px; fill: #e5e7eb; }
    .pin { fill: #1f2937; stroke: #9ca3af; }
    .wire { stroke: #22c55e; stroke-width: 3; }
    .wire-gnd { stroke: #ef4444; stroke-width: 3; }
    .led { fill: #ef4444; }
    .res { fill: #f59e0b; }
    .btn { fill: #374151; }
    .num { font-size: 16px; fill: #facc15; font-weight: bold; }

    /* Schema logique */
    .logic { font-family: 'Courier New', monospace; background: #0f172a; padding: 15px; border-radius: 6px; font-size: 1em; line-height: 1.8; }

    /* Code */
    .code-block { position: relative; }
    .code-block pre { background: #0f172a; border: 1px solid #1e3a5f; border-radius: 8px; padding: 20px; padding-top: 45px; overflow-x: auto; margin: 10px 0; }
    pre { background: #0f172a; border: 1px solid #1e3a5f; border-radius: 8px; padding: 20px; overflow-x: auto; margin: 10px 0; }
    code { font-family: 'Courier New', monospace; font-size: 0.95em; color: #e5e7eb; }
    .kw { color: #c084fc; }
    .fn { color: #60a5fa; }
    .cm { color: #6b7280; }
    .num-lit { color: #f59e0b; }
    .str { color: #34d399; }

    /* Bouton copier */
    .btn-copy {
      position: absolute; top: 8px; right: 8px;
      background: #374151; color: #e5e7eb; border: 1px solid #4b5563;
      padding: 6px 14px; border-radius: 6px; cursor: pointer;
      font-size: 0.85em; font-family: 'Segoe UI', Arial, sans-serif;
      transition: background 0.2s, border-color 0.2s;
    }
    .btn-copy:hover { background: #4b5563; border-color: #60a5fa; }
    .btn-copy.copied { background: #166534; border-color: #22c55e; color: #22c55e; }
  </style>
</head>
<body>
  <div class="container">

    <h1>Arduino - Bouton + LED</h1>

    <!-- Objectif -->
    <div class="section">
      <h2>Objectif</h2>
      <p>Allumer une LED lorsque l'on appuie sur un bouton.</p>
    </div>

    <!-- Materiel -->
    <div class="section">
      <h2>Materiel</h2>
      <ul>
        <li><span class="badge">1x</span> Arduino Uno</li>
        <li><span class="badge">1x</span> Bouton poussoir (4 broches)</li>
        <li><span class="badge">1x</span> LED</li>
        <li><span class="badge">1x</span> Resistance (220 Ohm)</li>
        <li>Fils de connexion</li>
      </ul>
    </div>

    <!-- Combinaisons possibles -->
    <div class="section">
      <h2>Combinaisons possibles (Pins / LEDs)</h2>
      <p style="color:#9ca3af; margin-bottom:15px;">Chaque pin controle 1 LED (ON/OFF). Formule : 2<sup>n</sup> combinaisons possibles.</p>
      <table style="width:100%; border-collapse:collapse; text-align:center;">
        <tr style="background:#1e3a8a;">
          <th style="padding:8px; border:1px solid #374151;">Pins</th>
          <th style="padding:8px; border:1px solid #374151;">Formule</th>
          <th style="padding:8px; border:1px solid #374151;">Combinaisons</th>
          <th style="padding:8px; border:1px solid #374151;">Valeurs</th>
        </tr>
        <?php
        $pins = [1,2,3,4,5,6,7,8,9,10,13];
        foreach ($pins as $p) {
          $comb = pow(2, $p);
          $max = $comb - 1;
          $style = ($p == 7) ? 'background:#166534; font-weight:bold;' : 'background:#0f172a;';
          echo "<tr style=\"$style\">";
          echo "<td style=\"padding:6px; border:1px solid #374151;\">$p</td>";
          echo "<td style=\"padding:6px; border:1px solid #374151;\">2<sup>$p</sup></td>";
          echo "<td style=\"padding:6px; border:1px solid #374151;\">$comb</td>";
          echo "<td style=\"padding:6px; border:1px solid #374151;\">0 - $max</td>";
          echo "</tr>";
        }
        ?>
      </table>
      <p style="color:#9ca3af; margin-top:15px; font-size:0.9em;">Exemple avec 7 LEDs : etat 42 = 0101010 en binaire</p>
      <div class="conn" style="margin-top:10px;">
        Etat 42 = 0 1 0 1 0 1 0<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;LED7=OFF LED6=ON LED5=OFF LED4=ON LED3=OFF LED2=ON LED1=OFF
      </div>
      <p style="color:#9ca3af; margin-top:10px; font-size:0.9em;">Arduino Uno : 13 pins digitaux + 6 analogiques = 19 pins max = 2<sup>19</sup> = 524 288 combinaisons</p>
    </div>

    <!-- Bouton -->
    <div class="section">
      <h2>Bouton (numerotation)</h2>
      <p>Vue de dessus :</p>
      <div class="btn-grid">
        <div class="btn-cell">B1</div>
        <div class="btn-cell">B2</div>
        <div class="btn-cell">B3</div>
        <div class="btn-cell">B4</div>
      </div>
      <h3>Connexions internes</h3>
      <div class="conn">
        B1 est relie a B3<br>
        B2 est relie a B4
      </div>
    </div>

    <!-- Branchement -->
    <div class="section">
      <h2>Branchement</h2>

      <h3>Cote Bouton</h3>
      <ul>
        <li><span style="color:#ef4444; font-weight:bold;">B1</span> &rarr; <span style="color:#ef4444;">Pin GND</span> (Arduino)</li>
        <li><span style="color:#22c55e; font-weight:bold;">B2</span> &rarr; <span style="color:#22c55e;">Pin 2</span> (Arduino)</li>
      </ul>
      <p style="color:#9ca3af; font-size:0.9em;">(ou equivalent : B3 &rarr; Pin GND et B4 &rarr; Pin 2)</p>

      <h3>Cote Arduino + LED</h3>
      <ul>
        <li><span style="color:#22c55e; font-weight:bold;">Pin 2</span> &rarr; <span style="color:#22c55e;">B2</span> (Bouton)</li>
        <li><span style="color:#22c55e; font-weight:bold;">Pin 13</span> &rarr; <span style="color:#22c55e;">LED+</span> (patte longue)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin GND</span> &rarr; <span style="color:#ef4444;">B1</span> (Bouton)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin GND</span> &rarr; Resistance &rarr; <span style="color:#f59e0b;">LED-</span> (patte courte)</li>
      </ul>

      <h3>LED - Comment reconnaitre LED+ et LED&ndash; ?</h3>
      <div style="display:flex; align-items:center; gap:30px; margin:15px 0; flex-wrap:wrap;">
        <!-- Vue reel -->
        <div style="text-align:center;">
          <p style="color:#9ca3af; font-size:0.85em; margin-bottom:5px;">Vue reelle</p>
          <svg viewBox="0 0 140 200" width="140" height="200">
            <!-- Corps LED -->
            <ellipse cx="70" cy="70" rx="28" ry="30" fill="#ef4444" stroke="#fff" stroke-width="2" />
            <ellipse cx="70" cy="70" rx="14" ry="15" fill="#fca5a5" />
            <!-- Cote plat (cathode) a gauche -->
            <line x1="42" y1="55" x2="42" y2="85" stroke="#facc15" stroke-width="3" />
            <text x="2" y="75" style="font-size:9px; fill:#facc15;">plat = LED&ndash;</text>
            <!-- Patte longue + a droite -->
            <line x1="82" y1="100" x2="82" y2="180" stroke="#22c55e" stroke-width="3" />
            <circle cx="82" cy="180" r="4" fill="#22c55e" />
            <text x="90" y="150" style="font-size:11px; fill:#22c55e; font-weight:bold;">longue</text>
            <text x="90" y="165" style="font-size:11px; fill:#22c55e; font-weight:bold;">= LED+</text>
            <!-- Patte courte - a gauche -->
            <line x1="58" y1="100" x2="58" y2="155" stroke="#ef4444" stroke-width="3" />
            <circle cx="58" cy="155" r="4" fill="#ef4444" />
            <text x="8" y="140" style="font-size:11px; fill:#ef4444; font-weight:bold;">courte</text>
            <text x="8" y="155" style="font-size:11px; fill:#ef4444; font-weight:bold;">= LED&ndash;</text>
          </svg>
        </div>
        <!-- Vue Tinkercad -->
        <div style="text-align:center;">
          <p style="color:#9ca3af; font-size:0.85em; margin-bottom:5px;">Vue Tinkercad</p>
          <svg viewBox="0 0 200 120" width="200" height="120">
            <!-- Corps LED horizontal -->
            <ellipse cx="100" cy="50" rx="30" ry="25" fill="#ef4444" stroke="#fff" stroke-width="2" />
            <ellipse cx="100" cy="50" rx="15" ry="12" fill="#fca5a5" />
            <!-- Patte gauche = LED- -->
            <line x1="70" y1="50" x2="20" y2="50" stroke="#ef4444" stroke-width="3" />
            <circle cx="20" cy="50" r="6" fill="#ef4444" stroke="#fff" stroke-width="1.5" />
            <text x="5" y="40" style="font-size:12px; fill:#ef4444; font-weight:bold;">LED&ndash;</text>
            <text x="5" y="75" style="font-size:10px; fill:#9ca3af;">gauche</text>
            <!-- Patte droite = LED+ -->
            <line x1="130" y1="50" x2="180" y2="50" stroke="#22c55e" stroke-width="3" />
            <circle cx="180" cy="50" r="6" fill="#22c55e" stroke="#fff" stroke-width="1.5" />
            <text x="163" y="40" style="font-size:12px; fill:#22c55e; font-weight:bold;">LED+</text>
            <text x="160" y="75" style="font-size:10px; fill:#9ca3af;">droite</text>
          </svg>
        </div>
        <div>
          <ul>
            <li><span style="color:#22c55e; font-weight:bold;">LED+</span> (droite / patte longue) &rarr; Pin 13</li>
            <li><span style="color:#ef4444; font-weight:bold;">LED&ndash;</span> (gauche / patte courte) &rarr; Resistance &rarr; Pin GND</li>
          </ul>
          <p style="color:#facc15; margin-top:10px; font-size:0.9em;">Sur Tinkercad : gauche = LED&ndash; / droite = LED+</p>
        </div>
      </div>
    </div>

    <!-- Schema logique -->
    <div class="section">
      <h2>Schema logique</h2>
      <div class="logic">
        Pin 2 &#9472;&#9472;&#9472; B2 (Bouton) &#9472;&#9472;&#9472; B1 &#9472;&#9472;&#9472; Pin GND<br><br>
        Pin 13 &#9472;&#9472;&#9472; LED+ &#9472;&#9472;&#9472; LED&ndash; &#9472;&#9472;&#9472; Resistance &#9472;&#9472;&#9472; Pin GND
      </div>
    </div>

    <!-- Schema visuel -->
    <div class="section">
      <h2>Schema visuel</h2>
      <div class="board">
        <svg viewBox="0 0 850 480">

          <!-- ===== ARDUINO ===== -->
          <rect x="30" y="100" width="220" height="180" rx="10" fill="#1e3a8a" stroke="#3b82f6" stroke-width="2" />
          <text x="85" y="200" style="font-size:18px; fill:#93c5fd; font-weight:bold;">Arduino UNO</text>

          <!-- Pin 2 -->
          <circle cx="250" cy="150" r="10" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="232" y="155" style="font-size:11px; fill:#fff; font-weight:bold;">P2</text>
          <text x="268" y="155" class="label">Pin 2</text>

          <!-- Pin 13 -->
          <circle cx="250" cy="200" r="10" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="229" y="205" style="font-size:10px; fill:#fff; font-weight:bold;">P13</text>
          <text x="268" y="205" class="label">Pin 13</text>

          <!-- Pin GND 1 (pour le bouton) -->
          <circle cx="250" cy="250" r="10" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="230" y="255" style="font-size:8px; fill:#fff; font-weight:bold;">GND</text>
          <text x="268" y="255" class="label">Pin GND</text>

          <!-- Pin GND 2 (pour la resistance) -->
          <circle cx="60" cy="250" r="10" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="40" y="255" style="font-size:8px; fill:#fff; font-weight:bold;">GND</text>
          <text x="30" y="275" class="label">Pin GND</text>

          <!-- ===== BOUTON ===== -->
          <rect x="520" y="100" width="100" height="100" rx="8" fill="#374151" stroke="#9ca3af" stroke-width="2" />
          <text x="535" y="90" class="label" style="font-weight:bold; font-size:16px;">Bouton</text>

          <!-- Broches du bouton = gros ronds -->
          <!-- B1 -->
          <circle cx="545" cy="125" r="12" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="535" y="130" style="font-size:11px; fill:#fff; font-weight:bold;">B1</text>
          <!-- B2 -->
          <circle cx="595" cy="125" r="12" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="585" y="130" style="font-size:11px; fill:#fff; font-weight:bold;">B2</text>
          <!-- B3 -->
          <circle cx="545" cy="175" r="12" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="535" y="180" style="font-size:11px; fill:#fff; font-weight:bold;">B3</text>
          <!-- B4 -->
          <circle cx="595" cy="175" r="12" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="585" y="180" style="font-size:11px; fill:#fff; font-weight:bold;">B4</text>

          <!-- Connexions internes bouton (pointilles) -->
          <line x1="545" y1="135" x2="545" y2="165" stroke="#facc15" stroke-width="2" stroke-dasharray="4,3" />
          <line x1="595" y1="135" x2="595" y2="165" stroke="#facc15" stroke-width="2" stroke-dasharray="4,3" />

          <!-- ===== LED ===== -->
          <!-- Lueur -->
          <circle cx="570" cy="340" r="22" fill="#ef4444" opacity="0.15" />
          <!-- Corps -->
          <circle cx="570" cy="340" r="16" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <circle cx="570" cy="340" r="8" fill="#fca5a5" />
          <text x="560" y="345" style="font-size:10px; fill:#fff; font-weight:bold;">LED</text>
          <!-- Patte LED+ (haut) -->
          <circle cx="555" cy="320" r="8" fill="#22c55e" stroke="#fff" stroke-width="1.5" />
          <text x="530" y="312" style="font-size:10px; fill:#22c55e; font-weight:bold;">LED+</text>
          <!-- Patte LED- (bas) -->
          <circle cx="585" cy="358" r="8" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <text x="575" y="380" style="font-size:10px; fill:#f59e0b; font-weight:bold;">LED-</text>

          <!-- ===== RESISTANCE ===== -->
          <rect x="620" y="380" width="90" height="16" rx="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <!-- Bandes -->
          <rect x="640" y="380" width="4" height="16" fill="#92400e" />
          <rect x="652" y="380" width="4" height="16" fill="#92400e" />
          <rect x="664" y="380" width="4" height="16" fill="#92400e" />
          <rect x="676" y="380" width="4" height="16" fill="#d4a017" />
          <text x="630" y="415" class="label" style="font-size:12px;">220 Ohm</text>
          <!-- Rond entree resistance -->
          <circle cx="620" cy="388" r="6" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <!-- Rond sortie resistance -->
          <circle cx="710" cy="388" r="6" fill="#ef4444" stroke="#fff" stroke-width="1.5" />

          <!-- ===== FILS (signal = vert) ===== -->

          <!-- Pin 2 Arduino --> B2 -->
          <line x1="260" y1="150" x2="583" y2="125" class="wire" />
          <!-- Rond de jonction Pin2-B2 -->
          <circle cx="583" cy="125" r="5" fill="#22c55e" />

          <!-- Pin 13 Arduino --> LED+ -->
          <line x1="260" y1="200" x2="440" y2="200" class="wire" />
          <line x1="440" y1="200" x2="440" y2="320" class="wire" />
          <line x1="440" y1="320" x2="547" y2="320" class="wire" />
          <!-- Ronds de jonction -->
          <circle cx="440" cy="200" r="5" fill="#22c55e" />
          <circle cx="440" cy="320" r="5" fill="#22c55e" />

          <!-- ===== FILS (GND = rouge) ===== -->
          <!-- 2 GND separes pour eviter de croiser les cables -->

          <!-- Pin GND --> B1 : fil direct -->
          <line x1="260" y1="250" x2="380" y2="250" class="wire-gnd" />
          <line x1="380" y1="250" x2="380" y2="125" class="wire-gnd" />
          <line x1="380" y1="125" x2="533" y2="125" class="wire-gnd" />
          <!-- Ronds de jonction -->
          <circle cx="380" cy="250" r="5" fill="#ef4444" />
          <circle cx="380" cy="125" r="5" fill="#ef4444" />

          <!-- GND2 (gauche Arduino) --> Resistance sortie : fil par le bas -->
          <line x1="50" y1="250" x2="50" y2="450" class="wire-gnd" />
          <line x1="50" y1="450" x2="750" y2="450" class="wire-gnd" />
          <line x1="750" y1="450" x2="750" y2="388" class="wire-gnd" />
          <line x1="750" y1="388" x2="716" y2="388" class="wire-gnd" />
          <!-- Ronds de jonction -->
          <circle cx="50" cy="450" r="5" fill="#ef4444" />
          <circle cx="750" cy="450" r="5" fill="#ef4444" />
          <circle cx="750" cy="388" r="5" fill="#ef4444" />

          <!-- LED- --> Resistance entree -->
          <line x1="593" y1="358" x2="610" y2="358" stroke="#f59e0b" stroke-width="3" />
          <line x1="610" y1="358" x2="610" y2="388" stroke="#f59e0b" stroke-width="3" />
          <line x1="610" y1="388" x2="620" y2="388" stroke="#f59e0b" stroke-width="3" />
          <!-- Rond de jonction -->
          <circle cx="610" cy="358" r="5" fill="#f59e0b" />

          <!-- ===== LEGENDE ===== -->
          <rect x="20" y="420" width="280" height="55" rx="6" fill="#1e293b" stroke="#374151" stroke-width="1" />
          <circle cx="40" cy="438" r="5" fill="#22c55e" stroke="#fff" stroke-width="1" />
          <text x="52" y="442" class="label" style="font-size:12px;">Signal (vert)</text>
          <circle cx="160" cy="438" r="5" fill="#ef4444" stroke="#fff" stroke-width="1" />
          <text x="172" y="442" class="label" style="font-size:12px;">GND (rouge)</text>
          <circle cx="40" cy="460" r="5" fill="#f59e0b" stroke="#fff" stroke-width="1" />
          <text x="52" y="464" class="label" style="font-size:12px;">Resistance</text>
          <circle cx="160" cy="460" r="5" fill="#facc15" stroke="#fff" stroke-width="1" />
          <text x="172" y="464" class="label" style="font-size:12px;">Connexion interne</text>

        </svg>
      </div>
    </div>

    <!-- Code Arduino 1 -->
    <div class="section">
      <h2>Programme 1 : LED allumee tant qu'on appuie</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">La LED s'allume quand on maintient le bouton, et s'eteint quand on relache.</p>
      <?php
$raw1 = <<<'ARDUINO'
int bouton = 2;
int led = 13;

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(led, OUTPUT);
}

void loop() {
  if (digitalRead(bouton) == LOW) {
    digitalWrite(led, HIGH);
  } else {
    digitalWrite(led, LOW);
  }
}
ARDUINO;
$code1 = htmlspecialchars($raw1);
$code1 = preg_replace('/\b(int|void|if|else|bool|boolean)\b/', '<span class="kw">$1</span>', $code1);
$code1 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay)\b/', '<span class="fn">$1</span>', $code1);
$code1 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code1);
$code1 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code1);
$code1 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code1);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code1; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw1); ?></textarea>
      </div>
    </div>

    <!-- Code Arduino 2 -->
    <div class="section">
      <h2>Programme 2 : Appui = allume / Re-appui = eteint (toggle)</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">Un appui allume la LED et elle reste allumee. Un deuxieme appui l'eteint. Comme un interrupteur.</p>
      <?php
$raw2 = <<<'ARDUINO'
int bouton = 2;
int led = 13;

bool etatLed = false;        // LED eteinte au depart
bool dernierAppui = HIGH;    // etat precedent du bouton

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(led, OUTPUT);
}

void loop() {
  bool appui = digitalRead(bouton);

  // On detecte le moment ou on appuie (passage HIGH -> LOW)
  if (appui == LOW && dernierAppui == HIGH) {
    etatLed = !etatLed;           // inverser l'etat de la LED
    digitalWrite(led, etatLed);   // appliquer le nouvel etat
    delay(200);                   // anti-rebond (evite les faux appuis)
  }

  dernierAppui = appui;  // memoriser l'etat du bouton
}
ARDUINO;
$code2 = htmlspecialchars($raw2);
$code2 = preg_replace('/\b(int|void|if|else|bool|boolean)\b/', '<span class="kw">$1</span>', $code2);
$code2 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay)\b/', '<span class="fn">$1</span>', $code2);
$code2 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code2);
$code2 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code2);
$code2 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code2);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code2; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw2); ?></textarea>
      </div>
    </div>

    <!-- Code Arduino 3 -->
    <div class="section">
      <h2>Programme 3 : Double appui = clignotement</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">1 appui = allume ou eteint la LED. 2 appuis rapides (moins de 500ms) = la LED clignote. Re-double appui = arrete le clignotement.</p>
      <?php
$raw3 = <<<'ARDUINO'
int bouton = 2;
int led = 13;

bool dernierEtat = HIGH;
int compteurAppui = 0;
unsigned long premierAppui = 0;
bool clignote = false;

unsigned long dernierClignote = 0;
bool ledAllumee = false;

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(led, OUTPUT);
}

void loop() {
  bool etat = digitalRead(bouton);

  // Detecter un nouvel appui (HIGH -> LOW)
  if (etat == LOW && dernierEtat == HIGH) {
    delay(50);  // anti-rebond

    compteurAppui++;

    if (compteurAppui == 1) {
      premierAppui = millis();
    }
  }

  dernierEtat = etat;

  // 2 appuis en moins de 1 seconde = activer/desactiver clignotement
  if (compteurAppui >= 2) {
    if (millis() - premierAppui < 500) {
      clignote = !clignote;  // toggle clignotement
      if (!clignote) {
        digitalWrite(led, LOW);  // eteindre si on arrete
      }
    }
    compteurAppui = 0;
  }

  // 1 seul appui et 1 seconde passee = toggle allume/eteint
  if (compteurAppui == 1 && millis() - premierAppui > 500) {
    compteurAppui = 0;
    if (clignote) {
      // Si on clignote, un simple appui arrete et eteint
      clignote = false;
      digitalWrite(led, LOW);
    } else {
      // Toggle normal : allume ou eteint
      ledAllumee = !ledAllumee;
      digitalWrite(led, ledAllumee);
    }
  }

  // Clignotement rapide sans bloquer
  if (clignote) {
    if (millis() - dernierClignote > 100) {
      ledAllumee = !ledAllumee;
      digitalWrite(led, ledAllumee);
      dernierClignote = millis();
    }
  }
}
ARDUINO;
$code3 = htmlspecialchars($raw3);
$code3 = preg_replace('/\b(int|void|if|else|bool|boolean|unsigned|long)\b/', '<span class="kw">$1</span>', $code3);
$code3 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay|millis)\b/', '<span class="fn">$1</span>', $code3);
$code3 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code3);
$code3 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code3);
$code3 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code3);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code3; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw3); ?></textarea>
      </div>
    </div>

    <!-- Code Arduino 4 -->
    <div class="section">
      <h2>Programme 4 : Appui long (2s) = allume 5s puis eteint 1s</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">Si on reste appuye plus de 2 secondes, la LED s'allume pendant 5 secondes puis s'eteint pendant 1 seconde, en boucle. Un appui normal arrete le cycle.</p>
      <?php
$raw4 = <<<'ARDUINO'
int bouton = 2;
int led = 13;

bool dernierAppui = HIGH;
unsigned long debutAppui = 0;  // quand on a commence a appuyer
bool boutonEnfonce = false;    // le bouton est maintenu ?
bool cyclage = false;          // mode 5s allume / 1s eteint actif ?

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(led, OUTPUT);
}

void loop() {
  bool appui = digitalRead(bouton);

  // On detecte le moment ou on appuie
  if (appui == LOW && dernierAppui == HIGH) {
    debutAppui = millis();
    boutonEnfonce = true;

    // Si le cyclage est actif, un appui l'arrete
    if (cyclage) {
      cyclage = false;
      digitalWrite(led, LOW);
      delay(200);
      dernierAppui = appui;
      return;
    }
  }

  // On detecte le moment ou on relache
  if (appui == HIGH && dernierAppui == LOW) {
    boutonEnfonce = false;
  }

  // Si on maintient le bouton plus de 2 secondes
  if (boutonEnfonce && millis() - debutAppui > 2000) {
    boutonEnfonce = false;
    cyclage = true;
  }

  // Mode cyclage : 5s allume, 1s eteint
  if (cyclage) {
    digitalWrite(led, HIGH);   // allume 5 secondes
    for (int i = 0; i < 50; i++) {
      delay(100);
      // Verifier si on appuie pour arreter
      if (digitalRead(bouton) == LOW) {
        cyclage = false;
        digitalWrite(led, LOW);
        delay(200);
        dernierAppui = HIGH;
        return;
      }
    }

    digitalWrite(led, LOW);    // eteint 1 seconde
    for (int i = 0; i < 10; i++) {
      delay(100);
      // Verifier si on appuie pour arreter
      if (digitalRead(bouton) == LOW) {
        cyclage = false;
        delay(200);
        dernierAppui = HIGH;
        return;
      }
    }
  }

  dernierAppui = appui;
}
ARDUINO;
$code4 = htmlspecialchars($raw4);
$code4 = preg_replace('/\b(int|void|if|else|bool|boolean|unsigned|long|for|return)\b/', '<span class="kw">$1</span>', $code4);
$code4 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay|millis)\b/', '<span class="fn">$1</span>', $code4);
$code4 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code4);
$code4 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code4);
$code4 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code4);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code4; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw4); ?></textarea>
      </div>
    </div>

    <!-- ================================================== -->
    <!-- CIRCUIT 2 : 2 LEDs avec bouton sequentiel          -->
    <!-- ================================================== -->

    <h1 style="margin-top:60px; border-top:2px solid #3b82f6; padding-top:30px;">Arduino - Bouton + 2 LEDs</h1>

    <!-- Objectif -->
    <div class="section">
      <h2>Objectif</h2>
      <p>Controler 2 LEDs avec un seul bouton : 1er appui = LED 1 s'allume, 2e appui = LED 2 s'allume, 3e appui = tout s'eteint.</p>
    </div>

    <!-- Materiel -->
    <div class="section">
      <h2>Materiel</h2>
      <ul>
        <li><span class="badge">1x</span> Arduino Uno</li>
        <li><span class="badge">1x</span> Bouton poussoir (4 broches)</li>
        <li><span class="badge">1x</span> LED rouge</li>
        <li><span class="badge">1x</span> LED verte</li>
        <li><span class="badge">2x</span> Resistance (220 Ohm)</li>
        <li>Fils de connexion</li>
      </ul>
    </div>

    <!-- Branchement -->
    <div class="section">
      <h2>Branchement</h2>

      <h3>Cote Bouton</h3>
      <ul>
        <li><span style="color:#ef4444; font-weight:bold;">B1</span> &rarr; <span style="color:#ef4444;">Pin GND</span> (Arduino)</li>
        <li><span style="color:#22c55e; font-weight:bold;">B2</span> &rarr; <span style="color:#22c55e;">Pin 2</span> (Arduino)</li>
      </ul>

      <h3>Cote Arduino + LEDs</h3>
      <ul>
        <li><span style="color:#22c55e; font-weight:bold;">Pin 2</span> &rarr; B2 (Bouton)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin 12</span> &rarr; LED rouge + (patte longue)</li>
        <li><span style="color:#22c55e; font-weight:bold;">Pin 13</span> &rarr; LED verte + (patte longue)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin GND</span> &rarr; B1 (Bouton)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin GND</span> &rarr; Resistance &rarr; LED rouge - (patte courte)</li>
        <li><span style="color:#ef4444; font-weight:bold;">Pin GND</span> &rarr; Resistance &rarr; LED verte - (patte courte)</li>
      </ul>
    </div>

    <!-- Schema logique -->
    <div class="section">
      <h2>Schema logique</h2>
      <div class="logic">
        Pin 2 &#9472;&#9472;&#9472; B2 (Bouton) &#9472;&#9472;&#9472; B1 &#9472;&#9472;&#9472; Pin GND<br><br>
        Pin 12 &#9472;&#9472;&#9472; LED rouge + &#9472;&#9472;&#9472; LED rouge - &#9472;&#9472;&#9472; Resistance &#9472;&#9472;&#9472; Pin GND<br><br>
        Pin 13 &#9472;&#9472;&#9472; LED verte + &#9472;&#9472;&#9472; LED verte - &#9472;&#9472;&#9472; Resistance &#9472;&#9472;&#9472; Pin GND
      </div>
    </div>

    <!-- Schema visuel -->
    <div class="section">
      <h2>Schema visuel</h2>
      <div class="board">
        <svg viewBox="0 0 850 550">

          <!-- ===== ARDUINO ===== -->
          <rect x="30" y="100" width="220" height="220" rx="10" fill="#1e3a8a" stroke="#3b82f6" stroke-width="2" />
          <text x="85" y="220" style="font-size:18px; fill:#93c5fd; font-weight:bold;">Arduino UNO</text>

          <!-- Pin 2 -->
          <circle cx="250" cy="140" r="10" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="232" y="145" style="font-size:11px; fill:#fff; font-weight:bold;">P2</text>
          <text x="268" y="145" class="label">Pin 2</text>

          <!-- Pin 12 -->
          <circle cx="250" cy="190" r="10" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="229" y="195" style="font-size:10px; fill:#fff; font-weight:bold;">P12</text>
          <text x="268" y="195" class="label">Pin 12</text>

          <!-- Pin 13 -->
          <circle cx="250" cy="240" r="10" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="229" y="245" style="font-size:10px; fill:#fff; font-weight:bold;">P13</text>
          <text x="268" y="245" class="label">Pin 13</text>

          <!-- Pin GND -->
          <circle cx="250" cy="290" r="10" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="230" y="295" style="font-size:8px; fill:#fff; font-weight:bold;">GND</text>
          <text x="268" y="295" class="label">Pin GND</text>

          <!-- GND 2 (gauche) -->
          <circle cx="60" cy="290" r="10" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="40" y="295" style="font-size:8px; fill:#fff; font-weight:bold;">GND</text>
          <text x="30" y="315" class="label">Pin GND</text>

          <!-- ===== BOUTON ===== -->
          <rect x="520" y="100" width="100" height="100" rx="8" fill="#374151" stroke="#9ca3af" stroke-width="2" />
          <text x="535" y="90" class="label" style="font-weight:bold; font-size:16px;">Bouton</text>

          <circle cx="545" cy="125" r="12" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="535" y="130" style="font-size:11px; fill:#fff; font-weight:bold;">B1</text>
          <circle cx="595" cy="125" r="12" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="585" y="130" style="font-size:11px; fill:#fff; font-weight:bold;">B2</text>
          <circle cx="545" cy="175" r="12" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <text x="535" y="180" style="font-size:11px; fill:#fff; font-weight:bold;">B3</text>
          <circle cx="595" cy="175" r="12" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <text x="585" y="180" style="font-size:11px; fill:#fff; font-weight:bold;">B4</text>

          <line x1="545" y1="135" x2="545" y2="165" stroke="#facc15" stroke-width="2" stroke-dasharray="4,3" />
          <line x1="595" y1="135" x2="595" y2="165" stroke="#facc15" stroke-width="2" stroke-dasharray="4,3" />

          <!-- ===== LED ROUGE (pin 12) ===== -->
          <circle cx="480" cy="320" r="22" fill="#ef4444" opacity="0.15" />
          <circle cx="480" cy="320" r="16" fill="#ef4444" stroke="#fff" stroke-width="2" />
          <circle cx="480" cy="320" r="8" fill="#fca5a5" />
          <text x="456" y="360" style="font-size:11px; fill:#ef4444; font-weight:bold;">LED rouge</text>
          <!-- LED+ -->
          <circle cx="465" cy="300" r="8" fill="#22c55e" stroke="#fff" stroke-width="1.5" />
          <text x="438" y="292" style="font-size:10px; fill:#22c55e; font-weight:bold;">LED+</text>
          <!-- LED- -->
          <circle cx="495" cy="338" r="8" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <text x="508" y="343" style="font-size:10px; fill:#f59e0b; font-weight:bold;">LED-</text>

          <!-- ===== LED VERTE (pin 13) ===== -->
          <circle cx="660" cy="320" r="22" fill="#22c55e" opacity="0.15" />
          <circle cx="660" cy="320" r="16" fill="#22c55e" stroke="#fff" stroke-width="2" />
          <circle cx="660" cy="320" r="8" fill="#86efac" />
          <text x="636" y="360" style="font-size:11px; fill:#22c55e; font-weight:bold;">LED verte</text>
          <!-- LED+ -->
          <circle cx="645" cy="300" r="8" fill="#22c55e" stroke="#fff" stroke-width="1.5" />
          <text x="618" y="292" style="font-size:10px; fill:#22c55e; font-weight:bold;">LED+</text>
          <!-- LED- -->
          <circle cx="675" cy="338" r="8" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <text x="688" y="343" style="font-size:10px; fill:#f59e0b; font-weight:bold;">LED-</text>

          <!-- ===== RESISTANCE 1 (LED rouge) ===== -->
          <rect x="440" y="400" width="90" height="16" rx="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <rect x="460" y="400" width="4" height="16" fill="#92400e" />
          <rect x="472" y="400" width="4" height="16" fill="#92400e" />
          <rect x="484" y="400" width="4" height="16" fill="#92400e" />
          <rect x="496" y="400" width="4" height="16" fill="#d4a017" />
          <text x="450" y="435" class="label" style="font-size:11px;">220 Ohm</text>
          <circle cx="440" cy="408" r="6" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <circle cx="530" cy="408" r="6" fill="#ef4444" stroke="#fff" stroke-width="1.5" />

          <!-- ===== RESISTANCE 2 (LED verte) ===== -->
          <rect x="620" y="400" width="90" height="16" rx="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <rect x="640" y="400" width="4" height="16" fill="#92400e" />
          <rect x="652" y="400" width="4" height="16" fill="#92400e" />
          <rect x="664" y="400" width="4" height="16" fill="#92400e" />
          <rect x="676" y="400" width="4" height="16" fill="#d4a017" />
          <text x="630" y="435" class="label" style="font-size:11px;">220 Ohm</text>
          <circle cx="620" cy="408" r="6" fill="#f59e0b" stroke="#fff" stroke-width="1.5" />
          <circle cx="710" cy="408" r="6" fill="#ef4444" stroke="#fff" stroke-width="1.5" />

          <!-- ===== FILS SIGNAL (vert) ===== -->

          <!-- Pin 2 --> B2 -->
          <line x1="260" y1="140" x2="583" y2="125" class="wire" />
          <circle cx="583" cy="125" r="5" fill="#22c55e" />

          <!-- Pin 12 --> LED rouge+ -->
          <line x1="260" y1="190" x2="350" y2="190" class="wire" />
          <line x1="350" y1="190" x2="350" y2="300" class="wire" />
          <line x1="350" y1="300" x2="457" y2="300" class="wire" />
          <circle cx="350" cy="190" r="5" fill="#22c55e" />
          <circle cx="350" cy="300" r="5" fill="#22c55e" />

          <!-- Pin 13 --> LED verte+ -->
          <line x1="260" y1="240" x2="420" y2="240" class="wire" />
          <line x1="420" y1="240" x2="420" y2="270" class="wire" />
          <line x1="420" y1="270" x2="645" y2="270" class="wire" />
          <line x1="645" y1="270" x2="645" y2="292" class="wire" />
          <circle cx="420" cy="240" r="5" fill="#22c55e" />
          <circle cx="420" cy="270" r="5" fill="#22c55e" />
          <circle cx="645" cy="270" r="5" fill="#22c55e" />

          <!-- ===== FILS GND (rouge) ===== -->

          <!-- Pin GND --> B1 -->
          <line x1="260" y1="290" x2="380" y2="290" class="wire-gnd" />
          <line x1="380" y1="290" x2="380" y2="125" class="wire-gnd" />
          <line x1="380" y1="125" x2="533" y2="125" class="wire-gnd" />
          <circle cx="380" cy="290" r="5" fill="#ef4444" />
          <circle cx="380" cy="125" r="5" fill="#ef4444" />

          <!-- GND2 --> Resistances (par le bas) -->
          <line x1="50" y1="290" x2="50" y2="500" class="wire-gnd" />
          <line x1="50" y1="500" x2="770" y2="500" class="wire-gnd" />
          <!-- Branche vers resistance 1 -->
          <line x1="530" y1="500" x2="530" y2="414" class="wire-gnd" />
          <circle cx="530" cy="500" r="5" fill="#ef4444" />
          <!-- Branche vers resistance 2 -->
          <line x1="710" y1="500" x2="710" y2="414" class="wire-gnd" />
          <circle cx="710" cy="500" r="5" fill="#ef4444" />
          <circle cx="50" cy="500" r="5" fill="#ef4444" />

          <!-- LED rouge- --> Resistance 1 -->
          <line x1="503" y1="338" x2="520" y2="338" stroke="#f59e0b" stroke-width="3" />
          <line x1="520" y1="338" x2="520" y2="408" stroke="#f59e0b" stroke-width="3" />
          <line x1="520" y1="408" x2="440" y2="408" stroke="#f59e0b" stroke-width="3" />
          <circle cx="520" cy="338" r="5" fill="#f59e0b" />

          <!-- LED verte- --> Resistance 2 -->
          <line x1="683" y1="338" x2="700" y2="338" stroke="#f59e0b" stroke-width="3" />
          <line x1="700" y1="338" x2="700" y2="408" stroke="#f59e0b" stroke-width="3" />
          <line x1="700" y1="408" x2="620" y2="408" stroke="#f59e0b" stroke-width="3" />
          <circle cx="700" cy="338" r="5" fill="#f59e0b" />

          <!-- ===== LEGENDE ===== -->
          <rect x="20" y="470" width="280" height="55" rx="6" fill="#1e293b" stroke="#374151" stroke-width="1" />
          <circle cx="40" cy="488" r="5" fill="#22c55e" stroke="#fff" stroke-width="1" />
          <text x="52" y="492" class="label" style="font-size:12px;">Signal (vert)</text>
          <circle cx="160" cy="488" r="5" fill="#ef4444" stroke="#fff" stroke-width="1" />
          <text x="172" y="492" class="label" style="font-size:12px;">GND (rouge)</text>
          <circle cx="40" cy="510" r="5" fill="#f59e0b" stroke="#fff" stroke-width="1" />
          <text x="52" y="514" class="label" style="font-size:12px;">Resistance</text>
          <circle cx="160" cy="510" r="5" fill="#facc15" stroke="#fff" stroke-width="1" />
          <text x="172" y="514" class="label" style="font-size:12px;">Connexion interne</text>

        </svg>
      </div>
    </div>

    <!-- Tableau des etats -->
    <div class="section">
      <h2>Tableau des etats</h2>
      <table style="width:100%; border-collapse:collapse; text-align:center;">
        <tr style="background:#1e3a8a;">
          <th style="padding:8px; border:1px solid #374151;">Appui</th>
          <th style="padding:8px; border:1px solid #374151;">LED rouge (Pin 12)</th>
          <th style="padding:8px; border:1px solid #374151;">LED verte (Pin 13)</th>
        </tr>
        <tr style="background:#0f172a;">
          <td style="padding:6px; border:1px solid #374151;">Etat initial</td>
          <td style="padding:6px; border:1px solid #374151; color:#ef4444;">OFF</td>
          <td style="padding:6px; border:1px solid #374151; color:#ef4444;">OFF</td>
        </tr>
        <tr style="background:#1e293b;">
          <td style="padding:6px; border:1px solid #374151;">1er appui</td>
          <td style="padding:6px; border:1px solid #374151; color:#22c55e; font-weight:bold;">ON</td>
          <td style="padding:6px; border:1px solid #374151; color:#ef4444;">OFF</td>
        </tr>
        <tr style="background:#0f172a;">
          <td style="padding:6px; border:1px solid #374151;">2e appui</td>
          <td style="padding:6px; border:1px solid #374151; color:#22c55e; font-weight:bold;">ON</td>
          <td style="padding:6px; border:1px solid #374151; color:#22c55e; font-weight:bold;">ON</td>
        </tr>
        <tr style="background:#1e293b;">
          <td style="padding:6px; border:1px solid #374151;">3e appui</td>
          <td style="padding:6px; border:1px solid #374151; color:#ef4444;">OFF</td>
          <td style="padding:6px; border:1px solid #374151; color:#ef4444;">OFF</td>
        </tr>
        <tr style="background:#0f172a;">
          <td style="padding:6px; border:1px solid #374151;">4e appui</td>
          <td style="padding:6px; border:1px solid #374151; color:#9ca3af;">Retour au 1er appui (cycle)</td>
          <td style="padding:6px; border:1px solid #374151; color:#9ca3af;">...</td>
        </tr>
      </table>
    </div>

    <!-- Programme 1 : 2 LEDs sequentielles -->
    <div class="section">
      <h2>Programme 1 : 2 LEDs sequentielles</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">1er appui = LED rouge s'allume. 2e appui = LED verte s'allume aussi. 3e appui = tout s'eteint. Et ca recommence.</p>
      <?php
$raw5 = <<<'ARDUINO'
int bouton = 2;
int ledRouge = 12;
int ledVerte = 13;

bool dernierAppui = HIGH;
int etape = 0;  // 0 = tout eteint, 1 = rouge, 2 = rouge + verte

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(ledRouge, OUTPUT);
  pinMode(ledVerte, OUTPUT);
}

void loop() {
  bool appui = digitalRead(bouton);

  // Detecter le moment ou on appuie (HIGH -> LOW)
  if (appui == LOW && dernierAppui == HIGH) {
    etape++;

    if (etape == 1) {
      // 1er appui : allumer LED rouge
      digitalWrite(ledRouge, HIGH);
    }
    else if (etape == 2) {
      // 2e appui : allumer LED verte (rouge reste allumee)
      digitalWrite(ledVerte, HIGH);
    }
    else {
      // 3e appui : tout eteindre et recommencer
      digitalWrite(ledRouge, LOW);
      digitalWrite(ledVerte, LOW);
      etape = 0;
    }

    delay(200);  // anti-rebond
  }

  dernierAppui = appui;
}
ARDUINO;
$code5 = htmlspecialchars($raw5);
$code5 = preg_replace('/\b(int|void|if|else|bool|boolean)\b/', '<span class="kw">$1</span>', $code5);
$code5 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay)\b/', '<span class="fn">$1</span>', $code5);
$code5 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code5);
$code5 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code5);
$code5 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code5);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code5; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw5); ?></textarea>
      </div>
    </div>

    <!-- Programme 2 : Double/Triple appui + clignotement -->
    <div class="section">
      <h2>Programme 2 : Double appui = clignotement / Triple appui = clignotement alterne</h2>
      <p style="color:#9ca3af; margin-bottom:10px;">
        1 appui = allume/eteint les LEDs en sequence (comme programme 1).<br>
        2 appuis rapides = les 2 LEDs clignotent ensemble.<br>
        3 appuis rapides = les LEDs clignotent chacune leur tour (alternance).<br>
        Re-double ou re-triple appui = arrete le clignotement.
      </p>
      <?php
$raw6 = <<<'ARDUINO'
int bouton = 2;
int ledRouge = 12;
int ledVerte = 13;

bool dernierEtat = HIGH;
int compteurAppui = 0;
unsigned long premierAppui = 0;

// Modes : 0 = normal, 1 = clignote ensemble, 2 = clignote alterne
int mode = 0;
int etape = 0;  // pour le mode normal (0, 1, 2)

unsigned long dernierClignote = 0;
bool ledEtat = false;

void setup() {
  pinMode(bouton, INPUT_PULLUP);
  pinMode(ledRouge, OUTPUT);
  pinMode(ledVerte, OUTPUT);
}

void loop() {
  bool etat = digitalRead(bouton);

  // Detecter un nouvel appui (HIGH -> LOW)
  if (etat == LOW && dernierEtat == HIGH) {
    delay(50);  // anti-rebond

    compteurAppui++;

    if (compteurAppui == 1) {
      premierAppui = millis();
    }
  }

  dernierEtat = etat;

  // 3 appuis en moins de 800ms = clignotement alterne
  if (compteurAppui >= 3 && millis() - premierAppui < 800) {
    compteurAppui = 0;
    if (mode == 2) {
      // Deja en alterne : arreter
      mode = 0;
      etape = 0;
      digitalWrite(ledRouge, LOW);
      digitalWrite(ledVerte, LOW);
    } else {
      // Activer clignotement alterne
      mode = 2;
    }
    return;
  }

  // 2 appuis en moins de 500ms = clignotement ensemble
  if (compteurAppui >= 2 && millis() - premierAppui < 500) {
    compteurAppui = 0;
    if (mode == 1) {
      // Deja en clignotement : arreter
      mode = 0;
      etape = 0;
      digitalWrite(ledRouge, LOW);
      digitalWrite(ledVerte, LOW);
    } else {
      // Activer clignotement ensemble
      mode = 1;
    }
    return;
  }

  // 1 seul appui et delai depasse = action normale
  if (compteurAppui == 1 && millis() - premierAppui > 800) {
    compteurAppui = 0;

    // Si on est en mode clignotement, un simple appui arrete
    if (mode != 0) {
      mode = 0;
      etape = 0;
      digitalWrite(ledRouge, LOW);
      digitalWrite(ledVerte, LOW);
      return;
    }

    // Mode normal : cycle sequentiel
    etape++;
    if (etape == 1) {
      digitalWrite(ledRouge, HIGH);
    }
    else if (etape == 2) {
      digitalWrite(ledVerte, HIGH);
    }
    else {
      digitalWrite(ledRouge, LOW);
      digitalWrite(ledVerte, LOW);
      etape = 0;
    }
  }

  // Mode 1 : clignotement ensemble (les 2 en meme temps)
  if (mode == 1) {
    if (millis() - dernierClignote > 200) {
      ledEtat = !ledEtat;
      digitalWrite(ledRouge, ledEtat);
      digitalWrite(ledVerte, ledEtat);
      dernierClignote = millis();
    }
  }

  // Mode 2 : clignotement alterne (une puis l'autre)
  if (mode == 2) {
    if (millis() - dernierClignote > 200) {
      ledEtat = !ledEtat;
      digitalWrite(ledRouge, ledEtat);
      digitalWrite(ledVerte, !ledEtat);
      dernierClignote = millis();
    }
  }
}
ARDUINO;
$code6 = htmlspecialchars($raw6);
$code6 = preg_replace('/\b(int|void|if|else|bool|boolean|unsigned|long|return)\b/', '<span class="kw">$1</span>', $code6);
$code6 = preg_replace('/\b(pinMode|digitalRead|digitalWrite|delay|millis)\b/', '<span class="fn">$1</span>', $code6);
$code6 = preg_replace('/\b(INPUT_PULLUP|OUTPUT|LOW|HIGH|true|false)\b/', '<span class="str">$1</span>', $code6);
$code6 = preg_replace('/\b(\d+)\b/', '<span class="num-lit">$1</span>', $code6);
$code6 = preg_replace('/(\/\/.*)/', '<span class="cm">$1</span>', $code6);
?>
      <div class="code-block">
        <button class="btn-copy" onclick="copierCode(this)">Copier</button>
        <pre><code><?php echo $code6; ?></code></pre>
        <textarea class="code-raw" style="display:none"><?php echo htmlspecialchars($raw6); ?></textarea>
      </div>
    </div>

  </div>

  <script>
  function copierCode(btn) {
    var texte = btn.parentElement.querySelector('.code-raw').value;

    // Methode 1 : clipboard API (HTTPS / localhost)
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function() {
        succes(btn);
      }).catch(function() {
        fallback(btn, texte);
      });
    } else {
      fallback(btn, texte);
    }
  }

  function fallback(btn, texte) {
    // Methode 2 : textarea temporaire + execCommand
    var ta = document.createElement('textarea');
    ta.value = texte;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    var ok = document.execCommand('copy');
    document.body.removeChild(ta);

    if (ok) {
      succes(btn);
    } else {
      // Methode 3 : popup avec le code a copier manuellement
      prompt('Copie le code avec Ctrl+C :', texte);
    }
  }

  function succes(btn) {
    btn.textContent = 'Copie !';
    btn.classList.add('copied');
    setTimeout(function() {
      btn.textContent = 'Copier';
      btn.classList.remove('copied');
    }, 2000);
  }
  </script>
</body>
</html>
