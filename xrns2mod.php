<?php

$in_file = 'test2.xrns';
$out_file = 'test2.mod';

//--------

$tmp_dir = './tmp'; // sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rns2mod';
$out_unpacked = 'test.xrns.xml';
$zip = new ZipArchive();
if(!$zip->open($in_file))
{
	die("Can't open the file");	
}

if(!$zip->extractTo($tmp_dir))
{
	die("Can't write to $tmp_dir");
}

Module_Utils::init();
$out_mod = new Module_Mod();

$song_xml_file = $tmp_dir . DIRECTORY_SEPARATOR . 'Song.xml';

$song_xml = new SimpleXMlElement(file_get_contents($song_xml_file));

$out_mod->title = xml_get($song_xml, "GlobalSongData/SongName");

$patterns = $song_xml->xpath('PatternSequence/PatternSequence/Pattern');
$orders = array();
foreach($patterns as $p)
{
	$orders[] = $p;
}
$out_mod->orders = $orders;

// patterns
$patterns_rns = $song_xml->xpath('PatternPool/Patterns/Pattern');
$patterns = array();

foreach($patterns_rns as $k => $rpat)
{
	$pattern = new Module_Pattern(64, 4); // somehow hardcoded for now // TODO read all and merge all events into 4 according to certain logic (?? maybe according to the track titles?)

	foreach($rpat->xpath('Tracks/PatternTrack') as $track_number => $t)
	{
		foreach($t->xpath('Lines/Line') as $line)
		{
			$line_number = (int) $line['index'];
			foreach($line->xpath('NoteColumns/NoteColumn') as $nc)
			{
				$note = xml_get($nc, 'Note');
				$instrument = (int) xml_get($nc, 'Instrument');
				$volume = xml_get($nc, 'Volume');
				
				if(!is_null($note))
				{
					$pattern->setNote($line_number, $track_number, Module_Utils::noteToNumber($note));
				}
				
				if(!is_null($instrument))
				{
					$pattern->setInstrument($line_number, $track_number, $instrument);
				}
				
				if(!is_null($volume))
				{
					$pattern->setEffect($line_number, $track_number, 0xC, $volume);
				}
				
			}
			foreach($line->xpath('EffectColumns/EffectColumn') as $ec)
			{
				$effect_number = xml_get($ec, 'Number');
				$effect_value = xml_get($ec, 'Value');
				
				if(is_null($volume) && (($effect_number != '00') || ($effect_number == '00' && $effect_value != '00')))
				{
					echo "EFFECT: $effect_number $effect_value\n";
					$effect_value_dec = base_convert(intval($effect_value), 16, 10);
					$mod_values = Module_Utils::renoiseEffectToModEffect($effect_number, $effect_value_dec);
					$pattern->setEffect($line_number, $track_number, $mod_values['effect'], $mod_values['value']);
				}				
			}
		}
	}
	$patterns[]= $pattern;
}
$out_mod->patterns = $patterns;

// samples ... one per instrument
$i = -1;
$samples = array();
$samples_dirs = scandir($tmp_dir . DIRECTORY_SEPARATOR . 'SampleData');

foreach($song_xml->Instruments->Instrument as $instrument)
{
	$i++;
	echo "instrument $i\n";
	// Check there's at least one sample; sometimes instruments are empty
	$Sample = $instrument->Samples->Sample;
	
	$sample_name = xml_get($instrument, 'Samples/Sample/Name');
	
	if(is_null($sample_name))
	{
		// set to null and continue with next instrument
		echo "Sample is null\n";
		$samples[]= null;
		continue;
	}
	
	// name
	// length
	// fine tune
	// volume
	// loop start
	// loop length
	
	var_dump($sample_name);
	echo "\n";
	
	$sample_fine_tune = $Sample->Finetune;
	$sample_volume = $Sample->Volume;
	$sample_loop_start = $Sample->LoopStart;
	if($Sample->LoopMode != 'Off') // check for forward only TODO
	{
		$sample_loop_length = $Sample->LoopEnd - $sample_loop_start;
	}
	else
	{
		$sample_loop_length = 0;
	}
	
	// Sample data ?
	
	// First find this instrument's samples data folder
	// It is something like Instrumentxy (...)
	$folder_prefix = sprintf("Instrument%02d", $i);
	for($j = 0; $j < count($samples_dirs); $j++)
	{
		$folder = $samples_dirs[$j];
		
		if(strpos($folder, $folder_prefix) !== FALSE)
		{
			echo "** found, stop search = $folder\n";
			break;
		}
	}
	
	$full_samples_folder = $tmp_dir . DIRECTORY_SEPARATOR . 'SampleData' . DIRECTORY_SEPARATOR . $folder;
	
	// Now in the folder, look for the sample; just pick the first one for simplicity
	$samples_list = scandir($full_samples_folder);
	foreach($samples_list as $entry)
	{
		if(preg_match('/^Sample00 /', $entry))
		{
			break;
		}
	}
	$full_sample_name = $full_samples_folder . DIRECTORY_SEPARATOR . $entry;
	echo "SMP NAME = $full_sample_name\n";

	// now convert them to wav
	$wav_name = $full_samples_folder . DIRECTORY_SEPARATOR . $entry . ".wav";
	$out = shell_exec("flac -sd \"$full_sample_name\" -f -o \"$wav_name\"");
	echo $out;
	
	// and to raw, signed, 8 bit, 8k Hz, mono...
	/*$raw_name = $full_samples_folder . DIRECTORY_SEPARATOR . $entry . ".raw";
	$cmd = "sox \"$wav_name\" -L -b 8 -r 8000 -c 1 -u \"$raw_name\"";
	echo $cmd . "\n";
	$out = shell_exec("sox \"$wav_name\" -s -r 8000 -c 1 \"$raw_name\"");
	echo $out . "\n";*/
	
	$raw_name = $full_samples_folder . DIRECTORY_SEPARATOR . $entry . ".raw";
	$cmd = "sox \"$wav_name\" -V3 -1 -r 8363 -c 1 -t raw \"$raw_name\"";
	echo $cmd . "\n";
	$out = shell_exec($cmd);
	echo $out . "\n";
	
	$sample_length = filesize($raw_name);
	echo "SAMPLE LEN $sample_length\n";
	
	$sample_data = file_get_contents($raw_name);
	
	// length (bytes) ^^^^ derive from sample data
	
	$samples[] = array(
		'name'		=>	$sample_name,
		'length'	=>	$sample_length,
		'fine_tune'	=>	$sample_fine_tune,
		'volume'	=>	$sample_volume,
		'loop_start'=>	$sample_loop_start,
		'loop_length'=>	$sample_loop_length,
		'data'		=>	$sample_data
	);
	
}


$out_mod->samples = $samples;

file_put_contents($out_file, $out_mod->getBinary());

////////////////


// return single element value
function xml_get($xml, $path)
{
	$v = $xml->xpath($path);
	if(count($v) == 0)
	{
		return null;
	}
	$t = array_shift($v);
	return (string)$t;
}



// ---

class Module_Utils
{
	static $note_numbers;
	
//	http://modplug.svn.sourceforge.net/viewvc/modplug/trunk/OpenMPT/soundlib/Tables.cpp?revision=223&view=markup
	static $protrackerPeriodTable = array(
		1712,1616,1524,1440,1356,1280,1208,1140,1076,1016,960,907,
    	856,808,762,720,678,640,604,570,538,508,480,453,
    	428,404,381,360,339,320,302,285,269,254,240,226,
    	214,202,190,180,170,160,151,143,135,127,120,113,
    	107,101,95,90,85,80,75,71,67,63,60,56,
    	53,50,47,45,42,40,37,35,33,31,30,28

	);
	
	static public function init()
	{
		self::$note_numbers = array();
		
		$tones = array('C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B');
		for($i = 0; $i < 120; $i++)
		{
			$digit = floor($i / 12);
			$letter = $tones[$i % 12];
			if(strlen($letter) == 1)
			{
				$letter .= "-";
			}
			self::$note_numbers[$i] = $letter.$digit;
			
		}
	}
	
	/**
	* convert a note in the C-5 style format to an internal number
	*/
	static public function notetoNumber($note)
	{
		return array_search($note, self::$note_numbers); // TODO
	}
	
	static public function noteToAmigaFrequency($note, $finetune = 0)
	{
		// From http://www.jalix.org/ressources/miscellaneous/formats/~vrac/XM.TXT
		$periodTab = array(
		907,900,894,887,881,875,868,862,856,850,844,838,832,826,820,814,
      808,802,796,791,785,779,774,768,762,757,752,746,741,736,730,725,
      720,715,709,704,699,694,689,684,678,675,670,665,660,655,651,646,
      640,636,632,628,623,619,614,610,604,601,597,592,588,584,580,575,
      570,567,563,559,555,551,547,543,538,535,532,528,524,520,516,513,
      508,505,502,498,494,491,487,484,480,477,474,470,467,463,460,457);
		$finetune16 = $finetune / 16;
		$fracfinetune = $finetune16 - (int)$finetune16;
		$period = ($periodTab[($note%12)*8 + $finetune16]*(1 - $fracfineTune)) +
             $periodTab[($note % 12)*8 + $finetune16]*($fracfinetune16)
            *16/2^($note/12);
		// ???TODO      (The period is interpolated for finer finetune values)
		$frequency = round(8363*1712 / $period);
		return $frequency;
	}
	
	static public function renoiseEffectToModEffect($effect_number, $effect_value)
	{
		$mod_effect = null;
		$mod_value = $effect_value;
		
		switch($effect_number)
		{
			// Arpeggio
			case '00':
				$mod_effect = 0x0;
				break;
			
			// Pitch slide up == Portamento up
			case '01':
				$mod_effect = 0x1;
				break;
			
			// Pitch slide down == Portamento down
			case '02':
				$mod_effect = 0x2;
				break;
			
			// Glide to note with step xx == Portamento to note
			case '05':
				$mod_effect = 0x3;
				break;
			
			// Vibrato speed x depth y
			case '0f':
				$mod_effect = 0x4;
				break;
				
			// 5 portamento to note with volume slide
			// 6 vibrato with volume slide
			// 7 tremolo
			
			// Sample offset
			case '09':
				$mod_effect = 0x9;
				break;
			
			// Volume slide up
			// The MOD effect is Axy, where x = slide up speed so that's why we shift the value 4 times to the left
			case '06':
				$mod_effect = 0xA;
				$mod_value = min(15, base_convert($mod_value, 16, 10)) << 4;
				break;
			
			// Volume slide down
			// The MOD effect is Axy, where y = slide down speed
			case '07':
				$mod_effect = 0xA;
				$mod_value = min(15, base_convert($mod_value, 16, 10));
				break;
			
			// jump to order b
			// set note volume c
			
			// pattern break d
			case 'FB':
				$mod_effect = 0xD;
				break;
			
			// fine portamento up e1
			// fine portamento down e2
			// vibrato control e4
			// pattern loop e6
			// tremolo control e7
			
			// retrigger note e9
			// Note: renoise has 0Exy where x is the volume change that we won't use, hence the &
			case '0E':
				$mod_effect = 0xE;
				$mod_value = 0x90 | (0xF0 & $mod_value);
				
			// fine volume slide up ea
			// fine volume slide down eb
			// note cut ec
			
			// note delay ed
			case '0D':
				$mod_effect = 0xE;
				$mod_value = 0xD0 | min(0xF, $mod_value);
			
			// pattern delay ee
			case 'FD':
				$mod_effect = 0xE;
				$mod_value = 0xE0 | min(0xF, $mod_value);
			
			// Set song speed fxx
			case 'F1':
				if($mod_value < 0x20)
				{
					$mod_effect = 0xF;
				}
				break;
			 
		}
		
		return array('effect' => $mod_effect, 'value' => $mod_value);
	}
	
}

class Module
{
	public $title;
	public $orders;
	public $patterns;
	public $samples;
}

class Module_Pattern
{
	protected $_data;
	
	public function __construct($rows, $tracks)
	{
		$this->rows = $rows;
		$this->tracks = $tracks;
		
		// 'semisparse' array
		$this->_data = array();
		for($i = 0; $i < $tracks; $i++)
		{
			$this->_data[$i] = array();
		}
	}
	
	public function setNote($row, $track, $value)
	{
		$this->initCell($row, $track);
		$this->_data[$track][$row]->note = $value;
	}
	
	public function getNote($row, $track)
	{
		if(!isset($this->_data[$track][$row]))
		{
			return null;
		}
		else
		return $this->_data[$track][$row]->note;		
	}
	
	public function setInstrument($row, $track, $value)
	{
		$this->initCell($row, $track);
		$this->_data[$track][$row]->instrument = $value;
	}
	
	public function getInstrument($row, $track)
	{
		if(!isset($this->_data[$track][$row]))
		{
			return null;
		}
		else
		return $this->_data[$track][$row]->instrument;
	}
	
	public function setEffect($row, $track, $effect_number, $effect_value)
	{
		echo "Set effect: L = $row, T = $track, E = $effect_number, V = $effect_value\n";
		$this->initCell($row, $track);
		$this->_data[$track][$row]->effect_number = $effect_number;
		$this->_data[$track][$row]->effect_value = $effect_value;
	}
	
	public function getEffectNumber($row, $track)
	{
		if(!isset($this->_data[$track][$row]))
		{
			return null;
		}
		else
		return $this->_data[$track][$row]->effect_number;
	}
	
	public function getEffectValue($row, $track)
	{
		if(!isset($this->_data[$track][$row]))
		{
			return null;
		}
		else
		return $this->_data[$track][$row]->effect_value;
	}
	
	protected function initCell($row, $track)
	{
		if(array_key_exists($row, $this->_data[$track]))
		{
			return;
		}
		
		$this->_data[$track][$row] = new Module_Pattern_Cell();
	}
	
}

class Module_Pattern_Cell
{
	public $note;
	public $instrument;
	public $volume;
	public $effect_number;
	public $effect_value;
}

class Module_Mod extends Module
{
	public function getBinary()
	{
		$out = '';
		
		// Title ------------
		// Store the title, capped to 20 bytes
		$title = substr($this->title, 0, 19);
		$title = str_pad($title, 19 - strlen($title), chr(0));
		$title{19} = chr(0);
		var_dump($title);

		$out .= $title;
						
		// Samples ----------
		// (assume 31) // TODO
		
		/*
		 read in 22 bytes,       store as SAMPLE_NAME
		- read in 2 bytes (word), store as SAMPLE_LENGTH  *   \
		- read in 1 byte,         store as FINE_TUNE      @   /\ IMPORTANT:
		- read in 1 byte,         store as VOLUME               } see key
		- read in 2 bytes (word), store as LOOP_START     *   \/   below
		- read in 2 bytes (word), store as LOOP_LENGTH    *   /
		*/
		for($i = 0; $i < 31; $i++)
		{
			$sample = '';
			//$sample .= str_pad('sample' . $i, 22, '-'); // something like sample0-------... until 22
			$sample .= str_pad($this->samples[$i]['name'], 22, chr(0));
			$len = $this->samples[$i]['length'];
			$len1 = $len >> 9;
			$len2 = $len >> 1;
			$sample .= sprintf("%c%c", $len1, $len2); // sample length (endianness...mmmm)
			$sample .= sprintf("%c", 0); // fine tune
			$sample .= sprintf("%c", 64); // volume
			$lstart = $this->samples[$i]['loop_start'];
			$sample .= sprintf("%c%c", $lstart >> 9, $lstart >> 1); // loop start
			$llen = $this->samples[$i]['loop_length'];
			$sample .= sprintf("%c%c", $llen >> 9, $llen >> 1); // loop length
			
			$out .= $sample;
		}
		
		// Orders ----------
		$ordnum = count($this->orders);
		$out .= chr($ordnum);
		$out .= chr(0); // this one is unused
		
		$orders = str_pad('', 128, chr(0));
		foreach($this->orders as $k => $o)
		{
			$orders{$k} = chr($o);
		}
		$out .= $orders;

		// Now we're at 1080, time to output the magic marker which says 
		// 'hey this is a mod'
		
		$out .= 'M.K.';
		
		// Pattern data -----
		/*
		ÚÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄ¿
		³ Byte 0    Byte 1   Byte 2   Byte 3  ³
		³ÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄ³
		³aaaaBBBB CCCCCCCCC DDDDeeee FFFFFFFFF³
		ÀÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÙ
		aaaaDDDD     = sample number
		BBBBCCCCCCCC = sample period value
		eeee         = effect number
		FFFFFFFF     = effect parameters
		
		- From this point, loop for as many times as NUMBER_OF_PATTERNS
			 - From this point, loop 64 * CHANNELS times (this equals 1 pattern)
			  - read 4 bytes
			  - store SAMPLE_NUMBER as    (byte0 AND 0F0h) + (byte2 SHR 4)
			  - store PERIOD_FREQUENCY as ((byte0 AND 0Fh) SHL 8) + byte1;
			  - store EFFECT_NUMBER as    byte2 AND 0Fh
			  - store EFFECT_PARAMETER as byte 3
			  - increment pattern pointer by 4 bytes
			 - end loop
		- end loop
		*/
		
		echo "Have " . count($this->patterns) . " patterns\n";
		
		foreach($this->patterns as $pattern)
		{
			for($row = 0; $row < 64; $row++)
			{
				for($track = 0; $track < 4; $track++)
				{
					$note = $pattern->getNote($row, $track);
					$instrument = $pattern->getInstrument($row, $track);
					$effect_number = $pattern->getEffectNumber($row, $track);
					$effect_value = $pattern->getEffectValue($row, $track);
					
					if($note)
					{
						// $noteamiga = Module_Utils::noteToAmigaFrequency($note, 0);
						$note2 = $note + 13;//+ 36;
						$noteamiga = $note2;
						if($noteamiga < 37) $noteamiga = 37;
						$noteamiga -= 37;
						
						// if($noteamiga >= 6*12) $noteamiga = 6*12-1;
						if($noteamiga >= count(Module_Utils::$protrackerPeriodTable))
						{
							$noteamiga = count(Module_Utils::$protrackerPeriodTable) - 1;
						}
						$noteamiga = Module_Utils::$protrackerPeriodTable[$noteamiga];
						
						//echo "$row $track , note = $note2 -> $noteamiga, instrument = $instrument\n";
										
					}
					else
					{
						$noteamiga = 0;
					}
					// somehow ok
					/*$aaaa = $instrument & 0xFF00;
					$bbbb = ($noteamiga & 0x0F00) >> 8;
					$cccccccc = $noteamiga & 0x00FF;
					$dddd = $instrument & 0x00FF;*/
					
					/*$b0 = ($noteamiga & 0xF00) >> 8;
					$b1 = (($noteamiga << 8) & 0xF0) >> 8;
					$b2 = ($instrument << 4) & 0xF0; // TODO (effect)
					$b3 = 0; // TODO*/
					
					//aaaaBBBB CCCCCCCCC DDDDeeee FFFFFFFFF
					/*$b0 = ($aaaa << 4) | $bbbb;
					$b1 = $cccccccc;
					$b2 = $dddd << 4; // effect TODO
					$b3 = 0; // TODO*/
					
					// http://modplug.svn.sourceforge.net/viewvc/modplug/trunk/OpenMPT/soundlib/Load_mod.cpp?revision=216&view=markup ~ line 460
					
					if(!is_null($instrument))
					{
						$instrument = $instrument + 1; // (it's 1-based)
					}
					
					$b0 = ($noteamiga >> 8 & 0x0F) | ($instrument & 0x10);
					$b1 = $noteamiga & 0xFF;
					$b2 = (($instrument & 0x0F) << 4) | ($effect_number & 0x0F);
					$b3 = $effect_value;
					//echo "EFFECT VALUE = " ; var_dump($b3) ;echo "\n";
					
					//$ka = $b0.$b1.$b2.$b3;
					//echo "$b0 $b1 $b2 $b3 $ka\n";
					/*// can this be even dirtier?? XD
					$out{strlen($out)} = $b0;
					$out{strlen($out)} = $b1;
					$out{strlen($out)} = $b2;
					$out{strlen($out)} = $b3;*/
					$out .= sprintf("%c%c%c%c", $b0, $b1, $b2, $b3);
				}
			}
		}
		
		// Sample data!!
		echo "HAVE " . count($this->samples) . " samples\n";
		
		for($i = 0; $i < count($this->samples); $i++)
		{
			$out .= $this->samples[$i]['data'];
		}
		
		return $out;
	}
}
?>
