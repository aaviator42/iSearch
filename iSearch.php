<?php
/*
iSearch.php
v1.3 - 2024-05-03

By @aaviator42
License: AGPLv3

Performs fuzzy keyword searches on inverted indices
*/

namespace iSearch;

// function to convert search query string into an array of words
function stringToArray($qString){
	
	// if passed an array, return it unmodified
	if(is_array($qString)){
		return $qString;
	}
	
	// string to lowercase
	$qString = strtolower($qString);
	
	// strip apostrophes
	$qString = preg_replace("/'/", "", $qString);
	
	// strip punctuation (replace with spaces)
	$qString = preg_replace("#[[:punct:]]#", " ", $qString);
		
	// strip whitespace (replace with spaces)
	$qString = preg_replace('!\s+!', ' ', $qString);

	// convert string to array
	$qArray = explode(" ", $qString);
	
	// remove empty elements
	$qArray = array_filter($qArray, fn($value) => !is_null($value) && $value !== '');
	
	return array_values(array_unique($qArray));
}

// function to add roots of query words to the query array
// probably not necessary if you're using fuzzy search
function rootWords($qArray){
	// copy of query array
	$qArrayTemp = $qArray;
	
	foreach($qArray as $word){
		if(strlen($word) <= 4){
			continue;
		}
		// cities -> city
		if(mb_substr($word, -3) === "ies"){
			$qArrayTemp[] = mb_substr($word, 0, -3) . 'y';
		} 
		// wives -> wife
		// wolves -> wolf
		else if (mb_substr($word, -3) === "ves"){
			$qArrayTemp[] = mb_substr($word, 0, -3) . 'f';
			$qArrayTemp[] = mb_substr($word, 0, -3) . 'fe';
		}
		// potatoes -> potato
		else if (mb_substr($word, -3) === "oes"){
			$qArrayTemp[] = mb_substr($word, 0, -2);
		}		
		// gasses -> gas
		// passes -> pass
		else if (mb_substr($word, -4) === "sses"){
			$qArrayTemp[] = mb_substr($word, 0, -3);
			$qArrayTemp[] = mb_substr($word, 0, -2);
		}
		// matches -> match
		// braces -> brace
		else if(mb_substr($word, -2) === "es"){
			$qArrayTemp[] = mb_substr($word, 0, -1);
			$qArrayTemp[] = mb_substr($word, 0, -2);
		} 
		// cars -> car
		else if (mb_substr($word, -1) === "s"){
			$qArrayTemp[] = mb_substr($word, 0, -1);
		}
		// playing -> play
		else if (mb_substr($word, -3) === "ing"){
			$qArrayTemp[] = mb_substr($word, 0, -3);
		}
		// played -> play
		// hated -> hate
		else if (mb_substr($word, -2) === "ed"){
			$qArrayTemp[] = mb_substr($word, 0, -2);
			$qArrayTemp[] = mb_substr($word, 0, -1);
		}
		// beautiful -> beauty
		else if (mb_substr($word, -4) === "iful"){
			$qArrayTemp[] = mb_substr($word, 0, -4) . 'y';
		}
		// cheerful -> cheerful
		else if (mb_substr($word, -3) === "ful"){
			$qArrayTemp[] = mb_substr($word, 0, -3) . 'y';
		}
		
	}
	
	return array_values(array_unique($qArrayTemp));
}

// function to add synonyms of query words to the query array
function addSynonyms($qArray, $thesaurus){
	// copy of query array
	$qArrayTemp = $qArray;
	
	// run for each word in query array
	foreach($qArray as $word){
		foreach($thesaurus as $group){
			if(in_array($word, $group)){
				// word is in current synonym group
				// add all synonyms to query array
				$qArrayTemp = array_merge($qArrayTemp, $group);
				// skip to next word
				continue 2;
			}
		}
	}
	
	// return query string with synonyms added
	return array_values(array_unique($qArrayTemp));
}

// function to add supplements of query words to the query array
function addSupplements($qArray, $supplements){
	// copy of query array
	$qArrayTemp = $qArray;
	
	
	// run for each word in query array
	foreach($qArray as $word){
		if(isset($supplements[$word])){
			$qArrayTemp = array_merge($qArrayTemp, $supplements[$word]);
		}
	}
	
	// return query string with supplements added
	return array_values(array_unique($qArrayTemp));
}

// function to drop words from the query array
function dropWords($qArray, $droplist){
	// remove words from droplist from query array
	$qArray = array_diff($qArray, $droplist);
	return array_values(array_unique($qArray));
}

// function to return list of inverted index entries required 
// to perform a search operation
function getSearchWords($qArray, $wordlist, $confidence = 100){
	// $wordlist = list of all words in inverted index
	// $qArray = array of words to search for in inverted index
	// $confidence = fuzziness? 100 = strict; <100 = fuzzy
		
	// $confidence can't be <0
	if($confidence < 0){
		$confidence = 0;
	}
	
	if($confidence >= 100){
		// no fuzziness
		// strict search - simply search for all words in qArray
		
		$searchWords = $qArray;
	} else {
		// fuzzy search
		// search for all words in $wordlist that are similar to the words in $qArray
		
		$searchWords = [];
		foreach($wordlist as $listWord){
			foreach($qArray as $qWord){
				$similarity;
				similar_text($qWord, $listWord, $similarity);
				if($similarity >= $confidence){
					$searchWords[] = $listWord;
				}
			}
		}
	}
	
	$searchWords = array_values(array_unique($searchWords));

	return $searchWords;
}

// function to obtain search results from a partial inverted index
function getSearchResults($index, $searchWords = [], $domain = []){
	// array with final results
	// we add to it as $item => $score
	// and at the end of the function convert scores to percentages
	// and add the word matches
	$results = [];
	
	// list of inverted index entries to search
	if(count($searchWords) > 0){
		$searchWords = array_unique($searchWords);
	} else {
		$searchWords = array_keys($index);
	}
		
	// every match found from the index is stored in this array
	$matchingItems = [];
	
	// for every match, the keywords that triggered the matched are stored in this array
	$keywordMatches = [];
	
	foreach($searchWords as $searchWord){
		// add all index items containing $searchWord to $matchingItems
		if(isset($index[$searchWord])){
			$matchingItems = array_merge($matchingItems, $index[$searchWord]);
	
			// for each item containing $searchWord, make an entry in $keywordMatches
			foreach($index[$searchWord] as $item){
				$keywordMatches[$item][] = $searchWord;
			}
		}
	}
		
	// unique matches from index
	$uniqueMatchingItems = array_unique($matchingItems);	
		
	// calculate the number of matches for every unique match
	foreach($uniqueMatchingItems as $item){
		if(count($domain) > 0){
			if(!in_array($item, $domain)){
				// item not in domain, skip
				continue;
			}
		}
		if(isset($results[$item])){
			$results[$item]++;
		} else {
			$results[$item] = 1;
		}
	}
	
	/*
	at this point $results looks like: 
	(
		[item_171] => 6
		[item_318] => 4
		[item_329] => 3
		[item_329] => 1
		[item_379] => 1
	)
	
	so that we can use scores in weighted average calculations, we would like to 
	normalise match scores for index items as percentages:
	%score = [(number of keywords found)/(total number of keywords)]*100
	*/
	
	// total number of words we searched for
	$searchWordCount = count($searchWords);
	
	// convert match scores into match score %
	foreach($results as $result => $score){
		// convert to %score
		$pScore = (count($keywordMatches[$result]) / $searchWordCount) * 100;
		
		// round to 2 decimal places
		$pScore = round($pScore, 2);
		
		// update score to %score
		$results[$result] = $pScore;
		
	}
	
	/*
	we are returning an array of index items, along with what % of search words they matched,
	and the words that were matches
	(
		[item_7204] => [ "score" => 16.67], ["matches" => ["red", "car", "blue"]],
		...
	)
	*/
	
	// sort results by descending number of matches
	arsort($results);
	
	$finalResults = [];
	
	// add matches for each item to the result array
	foreach($results as $result => $pScore){
		$finalResults[$result]['score'] = $pScore;
		$finalResults[$result]['matches'] = $keywordMatches[$result];
	}
	
	return $finalResults;

}


