<?php

function wpcf7_wpautop_substitute( $pee, $br = 1 ) {
	$pee = $pee . "\n"; // just to make things a little easier, pad the end
	$pee = preg_replace( '|<br />\s*<br />|', "\n\n", $pee );
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr)';
	$pee = preg_replace( '!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee );
	$pee = preg_replace( '!(</' . $allblocks . '>)!', "$1\n\n", $pee );
	$pee = str_replace( array( "\r\n", "\r" ), "\n", $pee ); // cross-platform newlines
	if ( strpos( $pee, '<object' ) !== false ) {
		$pee = preg_replace( '|\s*<param([^>]*)>\s*|', "<param$1>", $pee ); // no pee inside object/embed
		$pee = preg_replace( '|\s*</embed>\s*|', '</embed>', $pee );
	}
	$pee = preg_replace( "/\n\n+/", "\n\n", $pee ); // take care of duplicates
	// make paragraphs, including one at the end
	$pees = preg_split( '/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY );
	$pee = '';
	foreach ( $pees as $tinkle )
		$pee .= '<p>' . trim( $tinkle, "\n" ) . "</p>\n";
	$pee = preg_replace( '|<p>\s*?</p>|', '', $pee ); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace( '!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $pee );
	$pee = preg_replace( '|<p>|', "$1<p>", $pee );
	$pee = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee ); // don't pee all over a tag
	$pee = preg_replace( "|<p>(<li.+?)</p>|", "$1", $pee ); // problem with nested lists
	$pee = preg_replace( '|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee );
	$pee = str_replace( '</blockquote></p>', '</p></blockquote>', $pee );
	$pee = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee );
	$pee = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee );
	if ( $br ) {
		$pee = preg_replace_callback( '/<(script|style).*?<\/\\1>/s', create_function( '$matches', 'return str_replace("\n", "<WPPreserveNewline />", $matches[0]);' ), $pee );
		$pee = preg_replace( '|(?<!<br />)\s*\n|', "<br />\n", $pee ); // optionally make line breaks
		$pee = str_replace( '<WPPreserveNewline />', "\n", $pee );
	}
	$pee = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee );
	$pee = preg_replace( '!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee );
	if ( strpos( $pee, '<pre' ) !== false )
		$pee = preg_replace_callback( '!(<pre.*?>)(.*?)</pre>!is', 'clean_pre', $pee );
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	if ( function_exists( 'get_shortcode_regex' ) )
		$pee = preg_replace( '/<p>\s*?(' . get_shortcode_regex() . ')\s*<\/p>/s', '$1', $pee ); // don't auto-p wrap shortcodes that stand alone

	return $pee;
}

?>