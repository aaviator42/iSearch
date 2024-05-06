<?php
/*
iBuilder.php
v1.3 - 2024-05-03

By @aaviator42
License: AGPLv3

Functions to build and modify inverted indices
for search operations
*/

namespace iBuilder;

//convert forward index into inverted index
function generateIndex($fIndex){
	
	//array to store inverted index in
	$iIndex = [];
	
	//add each item from the forward index to the inverted index
	foreach($fIndex as $item => $tags){
		//remove duplicate tags
		$tags = array_unique($tags);
				
		//add item to each word's list in inverted index
		foreach($tags as $tag){
			$iIndex[$tag][] = $item;
		}
	}
	
	return $iIndex;
}

//add items to inverted index
function addToIndex($iIndex, $fIndex){
	
	//add each item from the forward index to the inverted index
	foreach($fIndex as $item => $tags){
		//remove duplicate tags
		$tags = array_unique($tags);
				
		//add item to each word's list in inverted index
		foreach($tags as $tag){
			if(isset($iIndex[$tag])){
				$iIndex[$tag] = array_values((array_merge($iIndex[$tag], [$item])));
			} else {
				$iIndex[$tag] = [$item];
			}
		}
	}
	
	return $iIndex;
}

//remove items from inverted index
function removeFromIndex($iIndex, $fIndex){		
	
	//remove each item in the forward index from the inverted index
	foreach($fIndex as $item => $tags){
		//remove duplicate tags
		$tags = array_unique($tags);
		
		//remove item from index for each tag
		foreach($tags as $tag){
			if(!isset($iIndex[$tag])){
				continue;
			}
			$iIndex[$tag] = array_values(array_unique(array_diff($iIndex[$tag], [$item])));
			
			if(count($iIndex[$tag]) === 0){
				//no items remain in the list for a particular tag,
				//so we remove the tag from the inverted index and the wordlist
				
				unset($iIndex[$tag]);
			}
			
		}
	}
		
	return $iIndex;
}
