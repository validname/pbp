<? 

$CSV_dbs = array();
const BUFFER_LENGTH = 65536;

// just open as file. No fileds must be in the first line!
function open_CSV($CSV_filename)
{
	global $CSV_dbs;

	if( !($CSV_fp = @fopen($CSV_filename, "r")) ) {
		echo "Error while opening CSV file ".$CSV_filename.PHP_EOL;
		return false;
	}

	$CSV_dbs[$CSV_fp] = array('buffer'=>false); 
	return $CSV_fp;
}

// delimiter = '\t'. Minimal quoting support. Returns indexed array with data, wo fields names.
function get_CSV_record($CSV_fp)
{
	global $CSV_dbs;

	if( !$CSV_dbs[$CSV_fp] )
		return false;

	$breaked_quote = false;
	$endless = true;
	while( $endless ) {
		$line_end_pos = false;
		if( $CSV_dbs[$CSV_fp]['buffer'] )	// buffer is not empty: check for full line
			$line_end_pos = strpos( $CSV_dbs[$CSV_fp]['buffer'], "\n" );
		elseif( feof($CSV_fp) ) {	// buffer is empty and EOF is reached: no data to read and parse
				return false;
		}
		// no full line in buffer: read file until line end or EOF
		while( !feof($CSV_fp) && $line_end_pos === false ) {
			$CSV_dbs[$CSV_fp]['buffer'] .= fread( $CSV_fp, BUFFER_LENGTH);
			$line_end_pos = strpos( $CSV_dbs[$CSV_fp]['buffer'], "\n" );
		}
		$buffer_length = strlen($CSV_dbs[$CSV_fp]['buffer']);
		// if EOF is reached, but buffer doesn't contain line end: let parse the whole buffer
		if( feof($CSV_fp) && $line_end_pos === false ) {
			$line_end_pos = $buffer_length;
		}
		$data = explode( "\t", substr($CSV_dbs[$CSV_fp]['buffer'], 0, $line_end_pos));
		if( $data && $data[0]=="" )
			$data = false;
		else { // check for quoted line feed
			$last_field = $data[count($data)-1];
			$last_field_length = strlen($last_field);
			if( $last_field_length && $last_field{0} == '"' && $last_field{$last_field_length-1} != '"' ) {	// last field begins with quote and doesn't ends with it
				$breaked_quote = true;
				$CSV_dbs[$CSV_fp]['buffer']{$line_end_pos} = "\000";	// change line feed to zero
			}
			else
				$endless = false;	// last field doesn't begin with quote - now it's normal full line
				if( $breaked_quote ) {
					$last_field = str_replace("\000", "\n", $last_field);	// change back zero to line feeds
					$data[count($data)-1] = substr($last_field, 1, $last_field_length-2);	// remove quotes. We doesn't check whether quote exists at the field end
				} else  {
					// check all fields for quoting
					foreach( $data as $tmp => $field ) {
							$field_length = strlen($field);
							if( $field_length && $field{0} == '"' && $field{$field_length-1} == '"' ) { // field is quoted
								if( strpos( $field, '"', 1) !== $field_length-1 ) // there is another quote inside field
									$data[$tmp] = substr( $field, 1, $field_length-2 );	// remove border quotes
									$data[$tmp] = str_replace( '""', '"', $data[$tmp]);	// remove doubled quotes from field
							}
					}
				}
		}
	}

	if( $line_end_pos + 1 <= $buffer_length )
		$CSV_dbs[$CSV_fp]['buffer'] = substr($CSV_dbs[$CSV_fp]['buffer'], $line_end_pos+1);
	else
		$CSV_dbs[$CSV_fp]['buffer'] = "";
	return $data;
}

// just close file
function close_CSV($CSV_fp)
{
	global $CSV_dbs;

	fclose($CSV_fp);
	unset($CSV_dbs[$CSV_fp]);
	return true; 
}
?>
