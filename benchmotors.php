<?php

    if(isset($_GET['comparator'])) {
        if(isset($_GET['links'])) {
            $motors = [];
            $linksParam = $_GET['links'];
            foreach($linksParam as $key => $linkParam) {
                if(strlen($linkParam) > 0) {
                    preg_match_all('/https?:\/\/[a-zA-Z]*\/benchmotors\.php\?/m', $linkParam, $matches, PREG_SET_ORDER, 0);
                    if(isset($matches[0])) {
                        $objectDecoded = json_decode(file_get_contents($linkParam . "&json=1"));
                        if($objectDecoded) {
                            $objectDecoded-> link = $linkParam;
                            $motors[] = $objectDecoded;
                        } else
                            echo "Le lien ".$key." renvoie un résultat incorrect !</br>";
                    } else
                        echo "Le lien ".$key." n'est pas un lien benchmotors !</br>";
                }
            }
            $comparatives = ["autonomy" => true,"maxThrust" => true, "autonomyMaxAmp" => false, "weight" => false, "ampForHovering" => false, "throttleForHovering" => false];
            foreach($comparatives as $key=>$comparative)
                $$key = ["motor" => null, "value" => null];
            foreach($motors as $motor) {
                foreach($comparatives as $key=>$comparative) {
                    if(isset($motor->$key))
                        $cond = $comparative ? $motor->$key > $$key["value"] : $motor->$key < $$key["value"];
                    if($cond || !$$key["value"]) {
                        $$key["motor"] = $motor;
                        $$key["value"] = $motor->$key;
                    }
                }
            }
            foreach($motors as $motor) { // On attribue 100 crédits de %
                $motor->score = 0;
                $motor->score += $motor->autonomy/$autonomy["value"] * 35; // On récompense l'autonomie
                if(isset($motor->maxThrust))
                    $motor->score += $motor->maxThrust/$maxThrust["value"] * 30; // On récompense la poussée max
                $motor->score -= $motor->throttleForHovering/$throttleForHovering["value"] * 25; // On pénalise les gaz stationnaire excessifs
                $motor->score -= $motor->weight/$weight["value"] * 10; // On pénalise le poids
                $motor->score = round($motor->score);
            }
            usort($motors, function($a, $b) {
                return $b->score - $a->score;
            });
        }
        ?>
        <div class="container">
            <form method="GET" id="form">
                <input type="hidden" name="comparator" value="1"/>
                <div class="params">
                    <div class="bloc">
                        <h3>Liste des liens benchmotors</h3>
                        <div class="specsM" id="listLinks"></div>
                        <button class="addLink" type="button" onclick="addField('')">Ajouter</button>
                    </div>
                </div>
                <button type="submit">Benchmark</button>
            </form>
        </div>
        <?php if(isset($comparatives)) { ?>
            <div class="result">
                <div style="color:red;">Classement catégories:</div>
                <div class="spec">==============================</div>
                <?php
                    foreach($comparatives as $key=>$comparative) {
                        if($$key["value"])
                            echo '<div class="spec"><div>'.$key.':</div> <a href="'.htmlspecialchars($$key["motor"]->link).'"> ' . htmlspecialchars($$key["motor"]->name) . '</a> => ' . $$key["value"].'</div>';
                    }
                ?>
                <div class="spec">==============================</div>
                <div style="color:red;">Classement score:</div>
                <div class="spec">==============================</div>
                <?php
                foreach($motors as $motor) {
                    echo '<div class="spec"><div><a href="'.htmlspecialchars($motor->link).'"> ' . htmlspecialchars($motor->name) . '</a></div> => ' . $motor->score.'</div>';
                }
                ?>
                <div class="spec">==============================</div>
                <div style="color:red;">Benchmarks:</div>
                <div class="spec">==============================</div>
                <div class="listBench">
                    <?php
                    foreach($motors as $motor) {
                        echo '<div class="miniBench">';
                        echo '<div class="spec"><div>Moteur:</div> ' . $motor->name . '</div>';
                        echo '<div class="spec"><div>Moteurs:</div> ' . $motor->motors . '</div>';
                        echo '<div class="spec"><div>Poids total:</div> ' . $motor->weight . 'g</div>';
                        echo '<div class="spec">==============================</div>';
                        echo '<div class="spec"><div>% décollage:</div> ' . round($motor->throttleForTakeOff, 3) . '%</div>';
                        echo '<div class="spec"><div>Consommation:</div> ' . round($motor->ampForTakeOff, 2) . 'A</div>';
                        echo '<div class="spec">==============================</div>';
                        echo '<div class="spec"><div>% vol stationnaire:</div> ' . round($motor->throttleForHovering, 3) . '%</div>';
                        echo '<div class="spec"><div>Consommation:</div> ' . round($motor->ampForHovering, 2) . 'A</div>';
                        echo '<div class="spec"><div>Autonomie:</div> ' . round($motor->autonomy, 2) . ' m</div>';
                        echo '<div class="spec">==============================</div>';
                        if (isset($motor->maxThrust))
                            echo '<div class="spec"><div>Poussée max:</div> ' . $motor->maxThrust . 'g</div>';
                        if (isset($motor->autonomyMaxAmp))
                            echo '<div class="spec"><div>Autonomie puissance max:</div> ' . round($motor->autonomyMaxAmp, 2) . ' minutes</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        <?php } ?>
        <script type='text/javascript'>
            var elements = 1;
            var url = new URL(window.location.href);
            var linkParams = url.searchParams.getAll("links[]");
            for(link of linkParams)
                if(link)
                    addField(link);
            function addField(value) {
                var container = document.getElementById("listLinks");
                var input = document.createElement("input");
                input.type = "text";
                input.className = "question";
                input.name = "links[]";
                if(value)
                    input.value = value;
                container.appendChild(input);
                var label = document.createElement("label");
                var labelValue = document.createElement("span");
                labelValue.textContent = "Lien " + elements;
                label.appendChild(labelValue);
                container.appendChild(label);
                elements++;
            }
        </script>
        <?php
    } else {
        if(!empty($_GET)) {
            $res = "Données invalides";
            if (isset($_GET['throttle'], $_GET['thrust'], $_GET['name'], $_GET['weight'], $_GET['batteryA'], $_GET['batteryC'], $_GET['motors'], $_GET['amp'], $_GET['weightMotor'])) { // Verif existence
                // Initialisation des variables
                $weightMotor = $_GET['weightMotor'];
                $throttle = $_GET['throttle'];
                $thrust = $_GET['thrust'];
                $name = $_GET['name'];
                $weight = $_GET['weight'];
                $batteryA = $_GET['batteryA'];
                $batteryC = $_GET['batteryC'];
                $amp = $_GET['amp'];
                $motors = $_GET['motors'];

                $numericParams = [$throttle, $thrust, $weight, $batteryA, $batteryC, $motors, $amp, $weightMotor];
                $valid = true;
                foreach ($numericParams as $numericParam)
                    (is_numeric($numericParam) && $numericParam > 0) ? '' : $valid = false; // Vérif numérique et cohérence
                ($throttle > 100) ? $valid = false : ''; // Puissance moteur déjà supérieure à 100

                if ($valid) { // Paramètres corrects
                    // Partie variables
                    $weight += ($motors * $weightMotor); // On ajoute le poids des moteurs

                    // Partie approximation
                    $thrustPerThrottle = $thrust / $throttle * $motors; // Approximation de la poussée par % de puissance selon le rendement donné
                    $ampPerThrottle = $amp / $throttle * $motors; // Approximation de la conso par % de puissance selon le rendement donné

                    // Partie décollage
                    $weightSuppForTakeOff = (($weight * 0.05) > 500) ? 500 : ($weight * 0.05); // Poids supplémentaire de 5% ou maxi 500g pour décollage
                    $throttleForTakeOff = ($weight + $weightSuppForTakeOff) / $thrustPerThrottle; // Estimation de la puissance nécessaire pour un décollage
                    $ampForTakeOff = ($ampPerThrottle * $throttleForTakeOff); // Estimation de la consommation d'un décollage

                    // Partie autonomie
                    $weightSuppForHovering = (($weight * 0.025) > 250) ? 250 : ($weight * 0.025); // Poids supplémentaire de 2.5% ou maxi 250g pour vol stationnaire
                    $throttleForHovering = ($weight + $weightSuppForHovering) / $thrustPerThrottle; // Estimation de la puissance nécessaire pour un vol stationnaire
                    $ampForHovering = ($ampPerThrottle * $throttleForHovering); // Estimation de la consommation d'un vol stationnaire
                    $autonomy = ((($batteryA * 0.8) / 1000) / $ampForHovering * 60); // Estimation de la consommation pour un maintien en vol stationnaire


                    if ($throttleForTakeOff > 100) // Les moteurs dépassent leur capacité de puissance théorique
                        $res = "Les moteurs ne peuvent pas faire décoller le drone (" . round($throttleForTakeOff, 3) . "%)";
                    else if (($batteryA / 1000) * $batteryC < $ampForTakeOff || ($batteryA / 1000) * $batteryC < $ampForHovering) // La batterie n'est pas suffisante
                        $res = "La batterie ne suffit pas à alimenter les moteurs pour un décollage ou un vol stationnaire";
                    else {
                        $bench = new \stdClass;
                        $bench->name = $name;
                        $bench->motors = $motors;
                        $bench->weight = $weight;
                        $bench->throttleForTakeOff = $throttleForTakeOff;
                        $bench->ampForTakeOff = $ampForTakeOff;
                        $bench->throttleForHovering = $throttleForHovering;
                        $bench->ampForHovering = $ampForHovering;
                        $bench->autonomy = $autonomy;

                        $res = "";
                        if (abs($throttleForTakeOff - $throttle) >= 10) {
                            $res .= '<div style="color:red;">Pour une meilleure approximation, fournissez les paramètres pour une puissance de ' . round($throttleForTakeOff) . '% environ </div>';
                            $res .= '<div class="spec">==============================</div>';
                        }
                        $res .= '<div class="spec"><div>Moteur:</div> ' . $name . '</div>';
                        $res .= '<div class="spec"><div>Nombre de moteurs:</div> ' . $motors . '</div>';
                        $res .= '<div class="spec"><div>Poids du vecteur:</div> ' . $weight . 'g</div>';
                        $res .= '<div class="spec">==============================</div>';
                        $res .= '<div class="spec"><div>Pourcentage pour décollage:</div> ' . round($throttleForTakeOff, 3) . '%</div>';
                        $res .= '<div class="spec"><div>Consommation décollage:</div> ' . round($ampForTakeOff, 2) . 'A</div>';
                        $res .= '<div class="spec">==============================</div>';
                        $res .= '<div class="spec"><div>Pourcentage vol stationnaire:</div> ' . round($throttleForHovering, 3) . '%</div>';
                        $res .= '<div class="spec"><div>Consommation vol stationnaire:</div> ' . round($ampForHovering, 2) . 'A</div>';
                        $res .= '<div class="spec"><div>Autonomie vol stationnaire:</div> ' . round($autonomy, 2) . ' minutes</div>';
                        $res .= '<div class="spec">==============================</div>';
                        if (isset($_GET['maxThrust']) && is_numeric($_GET['maxThrust']) && $_GET['maxThrust'] > 0) {
                            // Partie poussée max
                            $res .= '<div class="spec"><div>Poussée max:</div> ' . ($_GET['maxThrust'] * $motors) . 'g</div>';
                            $bench->maxThrust = $_GET['maxThrust'] * $motors;
                        }
                        if (isset($_GET['maxAmp']) && is_numeric($_GET['maxAmp']) && $_GET['maxAmp'] > 0) {
                            // Partie autonomie max
                            $autonomyMaxAmp = ($batteryA / 1000) / ($_GET['maxAmp'] * $motors) * 60;
                            $res .= '<div class="spec"><div>Autonomie puissance max:</div> ';
                            if (($batteryA / 1000) * $batteryC >= ($_GET['maxAmp'] * $motors)) {
                                $bench->autonomyMaxAmp = $autonomyMaxAmp;
                                $res .= round($autonomyMaxAmp, 2) . ' minutes';
                            } else {
                                $res .= 'Batterie insuffisante';
                            }
                            $res .= '</div>';
                        }
                        if (isset($_GET['json']) && $_GET['json'] == 1)
                            die(json_encode($bench));
                    }
                }
            }
        }?>
    <form method="GET">
        <button type="submit" name="comparator" value="1">Comparateur</button>
    </form>
    <div class="container">
	<form method="GET" id="form">
		<div class="params">
			<div class="bloc">
				<h3>Specs Vecteur</h3>
				<div class="specsD">
					<input type="text" class="question" name="weight" required autocomplete="off" <?=isset($_GET['weight'])?'value="'.$_GET['weight'].'"':''?> />
					<label for="weight"><span>Poids du vecteur sans moteur (g)</span></label>
					<input type="text" class="question" name="batteryA" required autocomplete="off" <?=isset($_GET['batteryA'])?'value="'.$_GET['batteryA'].'"':''?> />
					<label for="batteryA"><span>Puissance batterie (mAh)</span></label>
					<input type="text" class="question" name="batteryC" required autocomplete="off" <?=isset($_GET['batteryC'])?'value="'.$_GET['batteryC'].'"':''?> />
					<label for="batteryC"><span>Décharge batterie (C)</span></label>
					<input type="text" class="question" name="motors" required autocomplete="off" <?=isset($_GET['motors'])?'value="'.$_GET['motors'].'"':''?> />
					<label for="motors"><span>Nombre de moteurs</span></label>
				</div>
			</div>
			<hr style="height: 350px;">
			<div class="bloc">
				<h3>Specs Moteur</h3>
				<div class="specsM">
					<input type="text" class="question" name="name" required autocomplete="off" <?=isset($_GET['name'])?'value="'.htmlspecialchars($_GET['name']).'"':''?> />
					<label for="name"><span>Nom du moteur</span></label>
					<input type="text" class="question" name="weightMotor" required autocomplete="off" <?=isset($_GET['weightMotor'])?'value="'.$_GET['weightMotor'].'"':''?> />
					<label for="weightMotor"><span>Poids du moteur (g)</span></label>
					<input type="text" class="question" name="throttle" required autocomplete="off" <?=isset($_GET['throttle'])?'value="'.$_GET['throttle'].'"':''?> />
					<label for="throttle"><span>Puissance (%)</span></label>
					<input type="text" class="question" name="amp" required autocomplete="off" <?=isset($_GET['amp'])?'value="'.$_GET['amp'].'"':''?> />
					<label for="amp"><span>Amperage (A)</span></label>
					<input type="text" class="question" name="thrust" required autocomplete="off" <?=isset($_GET['thrust'])?'value="'.$_GET['thrust'].'"':''?> />
					<label for="thrust"><span>Poussée (g)</span></label>
					<input type="text" class="question" name="maxThrust" autocomplete="off" <?=isset($_GET['maxThrust'])?'value="'.$_GET['maxThrust'].'"':''?> />
					<label for="maxThrust"><span>Poussée Max (g) - Facultatif</span></label>
					<input type="text" class="question" name="maxAmp" autocomplete="off" <?=isset($_GET['maxAmp'])?'value="'.$_GET['maxAmp'].'"':''?> />
					<label for="maxAmp"><span>Amperage Max (A) - Facultatif</span></label>
				</div>
			</div>
		</div>
		<button type="submit">Calculer</button>
	</form>
	NB: L'amperage nécessaire au décollage est calculé selon le même rendement que la puissance et l'ampérage fournis, les valeurs sont théoriques mais également légèrement faussées.
	Le décollage se fait avec une marge de 5% de puissance supplémentaire vis-à-vis du poids jusqu'à 500g et l'autonomie 2.5% jusqu'à 250g.
    </div>
    <div class="result">
        <?=isset($res) ? $res : ""?>
    </div>
<?php } ?>

<style>
	.container {
		display: flex; 
		flex-direction: column;
		align-items: center;
		margin: 0 2em;
	}
	
	.container {
		margin-bottom: 2em;
	}
	
	.result, .miniBench {
		display: flex; 
		flex-direction: column;
		text-align: center;
		color: blue;
		font-size: 23px;
	}
	
	.result {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
	.result .spec div {
		display: inline;
		color: green;
	}

    .listBench {
        display: flex;
        flex-wrap: wrap;
    }
    .listBench .spec {
        display: inline;
    }

    .miniBench {
        font-size: 18px;
        border: 1px solid black;
        margin: .5em;
        padding: .5em;
    }

    .addLink {
        margin: 1em 0;
    }

html {
  width: 100%;
}

body {
  margin: 0 auto 0 auto;
  width: 90%;
  max-width: 1125px;
}

.params {
	display: flex;
	justify-content: space-around;
	align-items: center;
	width: 75%;
}

.bloc {
	margin: 0 1.5em 1.5em 1.5em;
}

form {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 100%;
}

form h3 {
	text-align: center;
}

.specsD, .specsM {
	display: flex;
	flex-direction: column;
}

input,
span,
label,
textarea {
  font-family: 'Ubuntu', sans-serif;
  display: block;
  margin: 10px;
  padding: 5px;
  border: none;
  font-size: 16px;
}

textarea:focus,
input:focus {
  outline: 0;
}

input.question,
textarea.question {
  min-width: 300px;
  height: 28px;
  font-size: 16px;
  font-weight: 300;
  border-radius: 2px;
  margin: 0;
  border: none;
  width: 80%;
  background: rgba(0, 0, 0, 0);
  transition: padding-top 0.2s ease, margin-top 0.2s ease;
  margin-top: 20px;
  overflow-x: hidden; /* Hack to make "rows" attribute apply in Firefox. */
}
input.question + label,
textarea.question + label {
  display: block;
  position: relative;
  white-space: nowrap;
  padding: 0;
  margin: 0;
  width: 100%;
  border-top: 1px solid red;
  -webkit-transition: width 0.4s ease;
  transition: width 0.4s ease;
  height: 0px;
}

input.question:focus + label,
textarea.question:focus + label {
  width: 80%;
}

input.question:focus,
input.question:valid {
  padding-top: 15px;
}

textarea.question:valid,
textarea.question:focus {
  margin-top: 15px;
}

input.question:focus + label > span,
input.question:valid + label > span {
  top: -45px;
  font-size: 16px;
  color: #333;
}

textarea.question:focus + label > span,
textarea.question:valid + label > span {
  top: -45px;
  font-size: 16px;
  color: #333;
}

input.question:valid + label,
textarea.question:valid + label {
  border-color: green;
}

input.question:invalid,
textarea.question:invalid {
  box-shadow: none;
}

input.question + label > span,
textarea.question + label > span {
  font-weight: 300;
  margin: 0;
  position: absolute;
  color: #8F8F8F;
  font-size: 16px;
  top: -33px;
  left: 0px;
  z-index: -1;
  -webkit-transition: top 0.2s ease, font-size 0.2s ease, color 0.2s ease;
  transition: top 0.2s ease, font-size 0.2s ease, color 0.2s ease;
}

input[type="submit"] {
  -webkit-transition: opacity 0.2s ease, background 0.2s ease;
  transition: opacity 0.2s ease, background 0.2s ease;
  display: block;
  opacity: 0;
  margin: 10px 0 0 0;
  padding: 10px;
  cursor: pointer;
}

input[type="submit"]:hover {
  background: #EEE;
}

input[type="submit"]:active {
  background: #999;
}

input.question:valid ~ input[type="submit"], textarea.question:valid ~ input[type="submit"] {
  -webkit-animation: appear 1s forwards;
  animation: appear 1s forwards;
}

input.question:invalid ~ input[type="submit"], textarea.question:invalid ~ input[type="submit"] {
  display: none;
}

@-webkit-keyframes appear {
  100% {
    opacity: 1;
  }
}

@keyframes appear {
  100% {
    opacity: 1;
  }
}

</style>