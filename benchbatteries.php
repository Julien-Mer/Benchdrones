<?php

    if(isset($_GET['comparator'])) {
        if(isset($_GET['links'], $_GET['motors'], $_GET['maxA'], $_GET['cells'], $_GET['batteryA'])) {
            $batteries = [];

            $linksParam = $_GET['links'];
            $motors = $_GET['motors'];
            $maxA = $_GET['maxA'];
            $cells = $_GET['cells'];

            $numericParams = [$motors, $maxA, $cells];
            $valid = true;
            foreach ($numericParams as $numericParam)
                (is_numeric($numericParam) && $numericParam > 0) ? '' : $valid = false; // Vérif numérique et cohérence

            preg_match('/([=><])[0-9]+([\,\.][0-9]*)?/', $_GET['batteryA'], $matches, PREG_OFFSET_CAPTURE, 0);
            if(!isset($matches[1]))
                $valid = false;
            else {
                $sign = substr($_GET['batteryA'], 0, 1);
                $batteryA = substr($_GET['batteryA'], 1);
            }

            // Partie variables
            $dischargeNeeded = $motors * $maxA;

            if($valid)
                foreach($linksParam as $key => $linkParam) {
                    if(strlen($linkParam) > 0) {
                        preg_match_all('/https?:\/\/[a-zA-Z]*\/benchbatteries\.php\?/m', $linkParam, $matches, PREG_SET_ORDER, 0);
                        if(isset($matches[0])) {
                            $objectDecoded = json_decode(file_get_contents($linkParam . "&json=1"));
                            if($objectDecoded) {
                                $objectDecoded->link = $linkParam;
                                $batteries[] = $objectDecoded;
                            } else
                                echo "Le lien ".$key." renvoie un résultat incorrect !</br>";
                        } else
                            echo "Le lien ".$key." n'est pas un lien benchbatteries !</br>";
                    }
                }
            else
                echo "Les données fournies sont invalides";

            $comparatives = ["ampPerGram" => true];
            foreach($comparatives as $key=>$comparative)
                $$key = ["battery" => null, "value" => null];
            foreach($batteries as $battery) {
                foreach($comparatives as $key=>$comparative) {
                    if(isset($battery->$key))
                        $cond = $comparative ? $battery->$key > $$key["value"] : $battery->$key < $$key["value"];
                    if(($cond || !$$key["value"]) && $dischargeNeeded <= $battery->dischargeCapacity) {
                        $$key["battery"] = $battery;
                        $$key["value"] = $battery->$key;
                    }
                }
            }
            foreach($batteries as $battery) { // On attribue 100 crédits de %
                $battery->score = 0;
                if($dischargeNeeded <= $battery->dischargeCapacity)
                    $battery->score += $battery->ampPerGram/$ampPerGram["value"] * 100; // On récompense l'autonomie
            }
            usort($batteries, function($a, $b) {
                return ($a->score > $b->score) ? -1 : 1;
            });

            // Pathfinding pour trouver toutes les combinaisons possibles
            $validPaths = [];
            function recurPath($path) {
                global $batteries, $sign, $batteryA, $cells, $dischargeNeeded, $validPaths;
                $pathCells = getSum($path, "cells");
                if($pathCells == $cells) {
                    $newObject = new \stdClass;
                    $newObject->path = $path;
                    $newObject->ampPerGram = getSum($path, "ampPerGram") / count($path);
                    $newObject->weight = getSum($path, "weight");
                    $newObject->capacity = getSum($path, "capacity");
                    if(!checkPathExists($validPaths, $newObject))
                        if($sign == "=" ? $newObject->capacity == $batteryA : ($sign == ">" ? $newObject->capacity > $batteryA : $newObject->capacity < $batteryA))
                            $validPaths[] = $newObject;
                } else {
                    foreach($batteries as $battery) { // Pour toutes les batteries
                        if(($battery->cells+$pathCells) <= $cells) // Si l'ajout de la batterie ne dépasse pas la taille des cellules
                            if($dischargeNeeded <= $battery->dischargeCapacity) { // Si la batterie a assez de décharge continue
                                $newPath = $path->getArrayCopy();
                                $newPath[] = $battery;
                                recurPath($newPath);
                            }
                    }
                }
            }

            function checkPathExists($validPaths, $path) {
                foreach($validPaths as $validPath) {
                    $same = true;
                    foreach ($path->path as $element) {
                        if (!in_array($element, $validPath->path)) {
                            $same = false;
                        }
                    }
                    if($same)
                        return true;
                }
                return false;
            }

            function getSum($path, $var) {
                $sum = 0;
                foreach($path as $element)
                    $sum += $element->$var;
                return $sum;
            }

            function getMin($path, $var) {
                $min = null;
                foreach($path as $element)
                    if($element->$var < $min || $min == null)
                        $min = $element->$var;
                return $min;
            }

            foreach($batteries as $battery) { // Pour toutes les batteries on lance la récursion
                if($battery->cells <= $cells && $dischargeNeeded <= $battery->dischargeCapacity) { // Si la batterie ne dépasse déjà pas les cells et qu'elle a assez de décharge continue
                    recurPath(new ArrayObject([$battery]));
                }
            }

            usort($validPaths, function($a, $b) {
                return ($a->ampPerGram > $b->ampPerGram) ? -1 : 1;
            });
        }
        ?>

        <div class="container">
            <form method="GET" id="form">
                <input type="hidden" name="comparator" value="1"/>
                <div class="params">
                    <div class="bloc">
                        <h3>Specs Vecteur</h3>
                        <div class="specsD">
                            <input type="text" class="question" name="motors" required autocomplete="off" <?=isset($_GET['motors'])?'value="'.$_GET['motors'].'"':''?> />
                            <label for="motors"><span>Nombre de moteurs</span></label>
                            <input type="text" class="question" name="maxA" required autocomplete="off" <?=isset($_GET['maxA'])?'value="'.$_GET['maxA'].'"':''?> />
                            <label for="maxA"><span>Pic max moteur (A)</span></label>
                            <input type="text" class="question" name="cells" required autocomplete="off" <?=isset($_GET['cells'])?'value="'.$_GET['cells'].'"':''?> />
                            <label for="cells"><span>Cellules (S)</span></label>
                            <input type="text" class="question" name="batteryA" required autocomplete="off" <?=isset($_GET['batteryA'])?'value="'.$_GET['batteryA'].'"':''?> />
                            <label for="batteryA"><span>Capacité voulue (mAh) (avec =, > ou <)</span></label>
                        </div>
                    </div>
                    <hr style="height: 350px;">
                    <div class="bloc">
                        <h3>Liste des liens benchbatteries</h3>
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
                            echo '<div class="spec"><div>'.$key.':</div> <a href="'.htmlspecialchars($$key["battery"]->link).'"> ' . htmlspecialchars($$key["battery"]->name) . '</a> => ' . $$key["value"].'</div>';
                    }
                ?>
                <div class="spec">==============================</div>
                <div style="color:red;">Classement score:</div>
                <div class="spec">==============================</div>
                <?php
                foreach($batteries as $battery) {
                    echo '<div class="spec"><div><a href="'.htmlspecialchars($battery->link).'"> ' . htmlspecialchars($battery->name) . '</a></div> => ' . $battery->score.'</div>';
                }
                ?>
                <div class="spec">==============================</div>
                <div style="color:red;">Benchmarks:</div>
                <div class="spec">==============================</div>
                <div class="spec"><div>Déchargement requis pour les moteurs: </div><?=$dischargeNeeded?>A</div>
                <div class="listBench">
                    <?php
                    foreach($batteries as $battery) {
                        echo '<div class="miniBench">';
                        echo '<div class="spec"><div>Batterie:</div> ' . $battery->name . '</div>';
                        if($dischargeNeeded > $battery->dischargeCapacity)
                            echo '<div class="spec"><div style="color: red;">NE SUFFIT PAS AUX MOTEURS</div></div>';
                        echo '<div class="spec">==============================</div>';
                        echo '<div class="spec"><div>Décharge cont. max.:</div> ' . $battery->dischargeCapacity . 'A</div>';
                        echo '<div class="spec"><div>Rapport mAh/g:</div> ' . round($battery->ampPerGram,2) . 'mAh/g</div>';
                        echo '<div class="spec"><div>Cellules:</div> ' . $battery->cells . 'S</div>';
                        echo '<div class="spec"><div>Capacité:</div> ' . $battery->capacity . 'mAh</div>';
                        echo '<div class="spec"><div>Poids:</div> ' . $battery->weight . 'g</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="spec">==============================</div>
                <div style="color:red;">Liste des meilleurs combos (6 max):</div>
                <div class="spec">==============================</div>
                <div class="listBench">
                    <?php
                    for($i = 0; $i < count($validPaths) && $i < 6; $i++) {
                        echo '<div class="miniBench">';
                        echo '<div class="spec"><div>Batteries:</div></div>';
                        foreach($validPaths[$i]->path as $battery) {
                            echo '<div class="spec"><div></div> ' . $battery->name . ' - '. $battery->capacity .'mAh '. $battery->cells . 'S</div>';
                        }
                        echo '<div class="spec">==============================</div>';
                        echo '<div class="spec"><div>Décharge cont. max. (pire batterie):</div> ' . getMin($validPaths[$i]->path, "dischargeCapacity") . 'A</div>';
                        echo '<div class="spec"><div>Rapport mAh/g:</div> ' . round($validPaths[$i]->ampPerGram,2) . 'mAh/g</div>';
                        echo '<div class="spec"><div>Capacité:</div> ' . $validPaths[$i]->capacity . 'mAh</div>';
                        echo '<div class="spec"><div>Poids:</div> ' . $validPaths[$i]->weight . 'g</div>';
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
            if (isset($_GET['name'], $_GET['weight'], $_GET['discharge'], $_GET['capacity'], $_GET['cells'])) { // Verif existence
                // Initialisation des variables
                $name = $_GET['name'];
                $weight = $_GET['weight'];
                $discharge = $_GET['discharge'];
                $capacity = $_GET['capacity'];
                $cells = $_GET['cells'];

                $numericParams = [$weight, $discharge, $capacity, $cells];
                $valid = true;
                foreach ($numericParams as $numericParam)
                    (is_numeric($numericParam) && $numericParam > 0) ? '' : $valid = false; // Vérif numérique et cohérence

                if ($valid) { // Paramètres corrects
                    // Partie variables
                    $ampPerGram = $capacity / $weight;
                    $dischargeCapacity = $discharge * ($capacity/1000);

                    $bench = new \stdClass;
                    $bench->name = $name;
                    $bench->weight = $weight;
                    $bench->cells = $cells;
                    $bench->discharge = $discharge;
                    $bench->ampPerGram = $ampPerGram;
                    $bench->dischargeCapacity = $dischargeCapacity;
                    $bench->capacity = $capacity;

                    $res = "";
                    $res .= '<div class="spec"><div>Batterie:</div> ' . $name . '</div>';
                    $res .= '<div class="spec">==============================</div>';
                    $res .= '<div class="spec"><div>Rendement mAh par g:</div> ' . round($ampPerGram, 2) . ' mAh</div>';
                    $res .= '<div class="spec"><div>Décharge continue maximale:</div> ' . $dischargeCapacity . 'A</div>';
                    $res .= '<div class="spec">==============================</div>';
                    if (isset($_GET['json']) && $_GET['json'] == 1)
                        die(json_encode($bench));
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
				<h3>Specs Batterie</h3>
				<div class="specsM">
					<input type="text" class="question" name="name" required autocomplete="off" <?=isset($_GET['name'])?'value="'.htmlspecialchars($_GET['name']).'"':''?> />
					<label for="name"><span>Nom de la batterie</span></label>
					<input type="text" class="question" name="weight" required autocomplete="off" <?=isset($_GET['weight'])?'value="'.$_GET['weight'].'"':''?> />
					<label for="weight"><span>Poids de la batterie (g)</span></label>
					<input type="text" class="question" name="discharge" required autocomplete="off" <?=isset($_GET['discharge'])?'value="'.$_GET['discharge'].'"':''?> />
					<label for="discharge"><span>Décharge (C)</span></label>
					<input type="text" class="question" name="capacity" required autocomplete="off" <?=isset($_GET['capacity'])?'value="'.$_GET['capacity'].'"':''?> />
					<label for="capacity"><span>Capacité (mAh)</span></label>
					<input type="text" class="question" name="cells" required autocomplete="off" <?=isset($_GET['cells'])?'value="'.$_GET['cells'].'"':''?> />
					<label for="cells"><span>Cellules (S)</span></label>
				</div>
			</div>
		</div>
		<button type="submit">Calculer</button>
	</form>
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