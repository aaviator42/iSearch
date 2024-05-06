# iSearch
A simple but powerful search engine, making use of inverted indices.

`v1.3`: `2024-05-03`  
License: `AGPLv3`

See it in action [here](https://aavi.xyz/proj/iSearch/)!

## What is this?

The successor to [Cha](https://github.com/aaviator42/Cha) and [dSearch](https://github.com/aaviator42/dSearch), iSearch makes use of [inverted indices](https://en.wikipedia.org/wiki/Inverted_index), which allows us to rapidly search much larger datasets without having to worry about fitting the entire search index in memory to perform a search.

It's under 400 lines of commented PHP code and supports fuzzy searching.


## How does it work?

Say you have the following dataset:

```php
// direct/forward index of items
$fIndex = [
    "img1" => ["sunset", "happy", "city", "skyline", "nature", "cloud"],
    "img2" => ["cat", "pet", "flower", "happy", "animal", "feline"],
    "img3" => ["tree", "nature", "green", "earth", "forest", "leaves"],
    "img4" => ["building", "grey", "city", "skyline", "sun", "urban"],
    "img5" => ["dog", "puppy", "animal", "happy", "nature", "pet"],
    "img6" => ["sky", "sun", "nature", "blue", "skyline", "cloud"],
    "img7" => ["beach", "sea", "ocean", "earth", "sunset", "blue"],
    "img8" => ["flower", "nature", "plant", "blossom", "sky", "green"]
];
```

... and that the user searches for "flower nature". When performing a search using a direct/forward index, we'd essentially loop through the tags for each item, make note of the entries that contain "flowers" and/or "nature", and return those entries as our result. 

This works pretty well for relatively small datasets, such as blogs, knowledge bases or wikis. But what if we want to search much larger datasets, with thousands of documents/items? It can be very computationally expensive or inefficient to load the entire dataset into memory and loop through it for each search. In these cases, we make use of an inverted index. Here's a (truncated) example, converted from the above forward index:

```php
// inverted index of items
$iIndex1 = [
    "nature" => ["img1", "img3", "img5", "img6", "img8"],
    "flower" => ["img2", "img8"],
    "sunset" => ["img1", "img7"],
    "happy" => ["img1", "img2", "img5"],
    "city" => ["img1", "img4"],
    "skyline" => ["img1", "img4", "img6"],
    "cloud" => ["img1", "img6"],
    "cat" => ["img2"],
    "pet" => ["img2", "img5"],
    "animal" => ["img2", "img5"],
    "feline" => ["img2"],
    [ ... ]
];
```

Notice how in the inverted index rather than to have item names corresponding to item tags, we have item tags corresponding to item names.

Now to perform the same search, we can simply look up "flower" and "nature" from the inverted index and get a list of items that contain these terms as tags (in this case, `["img8", "img2", "img1", "img3", "img5", "img6"]`). 

Imagine if each entry (row) of the above inverted index was stored as a separate file on disk. Now we only have to load the data for "flower" and "nature" into memory to perform the above search, which is obviously way more efficient per search. We do most of the computational work while generating the inverted index. To see the performance difference, try comparing the execution time for the same query between [dSearch](https://aavi.xyz/proj/dSearch/) and [iSearch](https://aavi.xyz/proj/iSearch/).

## Example usage

### A. Generating an inverted index

```php
<?php

// functions to generate and manipulate inverted indices
require 'iBuilder.php';

// a forward index of items
$fIndex = [
    "img1" => ["sunset", "happy", "city", "skyline", "nature", "cloud"],
    "img2" => ["cat", "pet", "flower", "happy", "animal", "feline"],
    "img3" => ["tree", "nature", "green", "earth", "forest", "leaves"],
    "img4" => ["building", "grey", "city", "skyline", "sun", "urban"],
    "img5" => ["dog", "puppy", "animal", "happy", "nature", "pet"],
    "img6" => ["sky", "sun", "nature", "blue", "skyline", "cloud"],
    "img7" => ["beach", "sea", "ocean", "earth", "sunset", "blue"],
    "img8" => ["flower", "nature", "plant", "blossom", "sky", "green"]
];

// convert $fIndex into an inverted index
$iIndex = \iBuilder\generateIndex($fIndex);

// write each entry of the inverted index to a separate file on disk
foreach($iIndex as $word => $items){
	file_put_contents('db/' . base64_encode($word) . '.dat', serialize($items));
}
```

### B. Performing a search

```php
<?php

// functions to search through inverted indices
require 'iSearch.php';

// string of words to search for
$qString = "sky flower nature";

// list of words that have an entry in the inverted index
$wordlist = unserialize(file_get_contents('db/_wordlist.dat'));

// convert the query string into an array of words
$qArray = \iSearch\stringToArray($qString);

// look up the query words in the wordlist, with fuzzy search enabled at a confidence of 85%
$searchWords = \iSearch\getSearchWords($qArray, $wordlist, 85);

// now $searchWords contains the terms for which we need to look up the inverted indices
$iIndex = [];
foreach($searchWords as $word){
	$filename = 'db/' . base64_encode($word) . '.dat';
	if(file_exists($filename)){
		$iIndex[$word] = unserialize(file_get_contents($filename));
	}
}

// we can now simply collate information from the inverted index to obtain our search results
$searchResults = \iSearch\getSearchResults($iIndex);

print_r($searchResults);

/* Output (simplified for example, read more below):

Array
(
    [img8] => 100
    [img6] => 66.67
    [img1] => 33.33
    [img3] => 33.33
    [img5] => 33.33
    [img2] => 33.33
)

*/
```

## Functions
This library consists of two files: `iBuilder.php` and `iSearch.php`. The former contains functions to generate and manipulate inverted indices. The latter contains functions to compile search results from inverted indices.

### 1. `iBuilder.php`

The following functions are contained in the `iBuilder` namespace. All return an inverted index in the form of a two-dimensional array.

#### 1.1 `generateIndex($fIndex)`

Converts a forward index into an inverted index. `$fIndex` is expected to be a forward index in the form of a two dimensional array, with item/document names/IDs corresponding to tags/words:
```php
require 'iBuilder.php';

$fIndex = [
    "item1" => ["tag1", "tag2", "tag3"],
    "item2" => ["tag3", "tag5"],
    "item3" => ["tag4", "tag2", "tag1", "tag3"],
    "item4" => ["tag5", "tag3"],
];

$iIndex = \iBuilder\generateIndex($fIndex);
```

Returns the inverted index generated from the forward index:

```php
[
    "tag1" => ["item1", "item3"],
    "tag2" => ["item1", "item3"],
    "tag3" => ["item1", "item2", "item3", "item4"],
    "tag5" => ["item2", "item4"],
    "tag4" => ["item3"],
];
```

(Small arrays used for demonstration, iSearch can work with _much_ larger datasets)

For typical use cases you probably want to do the following at this point:  
  1. Store the list of tags/words to disk (`array_keys($iIndex)`);
  2. Break the inverted index up into several parts and store them to disk. I like to use [StorX](https://github.com/aaviator42/StorX) for this purpose. In the usage examples above, we use one file per unique word in the index. This is generally a pretty solid approach, but you don't _have_ to do it this way, of course. For example, you could split the index on the first character of the words (so all words starting with `a` would go in `a.dat`, and so on).  

#### 1.2 `addToIndex($iIndex, $fIndex)`

Adds data from a forward index to an existing inverted index:

```php
$fIndex2 = [
    "item10" => ["tag1", "tag2", "tag3"],
    "item11" => ["tag2", "tag5", "tag4"],
    "item12" => ["tag5", "tag3"],
];

// add items from $fIndex2 to the existing inverted index
$iIndex2 = \iBuilder\addToIndex($iIndex, $fIndex2);

```
The first argument is expected to be an inverted index, generated with `generateIndex()`. The second argument is expected to be a forward index containing data to be added to the inverted index, in the same format as for `generateIndex()`.

Returns the expanded inverted index:
```php
[
    "tag1" => ["item1", "item3", "item10"],
    "tag2" => ["item1", "item3", "item10", "item11"],
    "tag3" => ["item1", "item2", "item3", "item4", "item10", "item12"],
    "tag5" => ["item2", "item4", "item11", "item12"],
    "tag4" => ["item3", "item11"],
];
```

#### 1.3 `removeFromIndex($iIndex, $fIndex)`

Removes entries contained in a forward index from an existing inverted index:

```php
$fIndex3 = [
    "item2" => ["tag3", "tag5"],
    "item3" => ["tag4", "tag2", "tag1", "tag3"],
	"item11" => ["tag2", "tag5", "tag4"],
    "item12" => ["tag5", "tag3"],
];

$iIndex3 = \iBuilder\removeFromIndex($iIndex2, $fIndex3);
```

Returns the reduced inverted index:

```php
[
    "tag1" => ["item1", "item10"],
    "tag2" => ["item1", "item10"],
    "tag3" => ["item1", "item4", "item10"],
    "tag5" => ["item4"],
];
```

Notice how since we're no longer indexing any items which contain "tag4" as a tag it has disappeared from our inverted index. 

### 2. `iSearch.php`

This file contains two kinds of functions: to manipulate query arrays, and to search inverted indices. The following functions are contained in the `iSearch` namespace.  None operate in-place. 

#### 2.1 `stringToArray($qString)`

Converts the search query string to an array of keywords/tags ("query array") and returns it. The entire string is converted into lowercase, and whitespace and punctuation is stripped from it.

Example usage:

```php
require 'iSearch.php';

$qString = $_POST["search_string"];

$qArray = \iSearch\stringToArray($qString);
```

We use this array of keywords to perform index lookups. 

#### 2.2 `rootWords($qArray)`

Attempts to find the root words of the words in the query array and returns the query array with them added to it. English specific, and not perfect (it's a weird language!), but does work for most cases.  Will sometimes add nonsensical keywords to the array, but that doesn't really impact the search functionality. Probably not necessary if you're using fuzzy searches.

Example:

```php
$qArray = ["beautiful", "colors", "cheerful", "puppies"];

$qArray = \iSearch\rootWords($qArray); // ['beautiful', 'colors', 'cheerful', 'puppies', 'beauty', 'color', 'cheery', 'puppy']
```

Types of conversions:

* cities → city
* wives → wife
* wolves → wolf
* potatoes → potato
* gasses → gas
* passes → pass
* matches → match
* braces → brace
* cars → car
* playing → play
* hated → hate
* played → play
* beautiful → beauty
* cheerful → cheer

#### 2.3 `addSynonyms($qArray, $thesaurus)`
Add synonyms of query words to the array and return it. If any of the words from a synonym group are contained in the array then all of them are added to it.

Expected format of `$thesaurus`:

```php
$thesaurus = [["big", "huge", "large"], ["small", "tiny"]];
```

Example:
```php
$qArray = ["pretty", "puppy"];
$thesaurus = [["beautiful", "pretty", "attractive"], ["puppy", "dog"]];

$qArray = \iSearch\addSynonyms($qArray, $thesaurus); // ['beautiful', 'puppy', 'pretty', 'attractive', 'dog']
```

#### 2.4 `addSupplements($qArray, $supplements)`
Unlike `addSynonyms()`, this is a "one-way" function. Use this when, for e.g., you want queries containing "cat" to always match with "domestic" and "pet", but not the other way around. 

Expected format of `$supplements`:

```php
$supplements = ["cat" => ["calilco", "animal", "pet", "domestic"], "red" => ["color"], "laptop" => ["gadget", "tech"]];
```

Example:
```php
$qArray = ["cat", "puppy"];
$supplements = ["cat" => ["calilco", "pet"], "dog" => ["puppy", "canine"]];

$qArray = \iSearch\addSupplements($qArray, $supplements); // ['cat', 'puppy', 'calico', 'pet']
```

#### 2.5 `dropWords($qArray, $droplist)`
Use this to remove words you don't want to search for from the quary array. (Eg: you can remove insignificant words (like "and", "of", "with", etc) from the query array for a performance bump.)

Expected format of `$droplist`:

```php
$droplist = ["of", "with", "along"];
```

A longer list of [stop words](https://en.wikipedia.org/wiki/Stop_word) can be found [here](https://gist.github.com/aaviator42/4d02e436d3c6e1aa0b51c7cab70ed083). 

Example:
```php
$qArray = ["cat", "puppy", "pet", "calico", "dog"];
$droplist = ["pet", "calico"];

$qArray = \iSearch\dropWords($qArray, $droplist); // ['cat', 'puppy', 'dog']
```

#### 2.6 `getSearchWords($qArray, $wordlist, $confidence = 100)`
This function looks up words from the query array in your inverted index's wordlist and tells you which entires from the inverted index you need to read to obtain your search results.

The first two arguments are expected to be one-dimensional arrays of words. 

**Fuzzy Searches:**  
The third argument, `confidence` (percentage) is optional. Valid values are `0 - 100`. Recommended values are `80-85`. Default value is `100`.  
Use this to control search fuzziness. 100% means that the function will only count a match if the spellings of the tag and keyword match exactly (unfuzzy search). 90% means that small typos will still match. And so on. Uses PHP's [`similar_text()`](https://www.php.net/manual/en/function.similar-text.php).

Example:
```php
$qArray = ["cats", "puppies", "pets"];
$wordlist = ["calico", "beagle", "puppy", "calico", "cat", "domesticated", "pet", "animal", "feline", "canine"];

$searchWords = \iSearch\getSearchWords($qArray, $wordlist, 85); // ['cat', 'pet']

$qArray = \iSearch\rootWords($qArray); // add root words to query array

$searchWords = \iSearch\getSearchWords($qArray, $wordlist, 85); // ['cat', 'puppy', 'pet']
```

##### 2.7 `getSearchResults($index, $searchWords = [], $domain = [])`

This function compiles data from the supplied inverted index and returns search results. 

Expected inverted index format (as generated by functions in `iBuilder.php`):

```php
[
    "tag1" => ["item1", "item3"],
    "tag2" => ["item1", "item3"],
    "tag3" => ["item1", "item2", "item3", "item4"],
    "tag5" => ["item2", "item4"],
    "tag4" => ["item3"],
    [ ... ]
];
```

If the supplied index contains entries for more than just the words returned by `getSearchWords()` then you should also pass the output of that function as the second argument. If your inverted index only contains entires for words returned by `getSearchWords()` then you can omit this. 

If you only want the results to include items from a subset of the index, then you can specify a set of items/documents as the domain in the third argument (eg: if we specify `['item1', 'item5', 'item10']` as the domain then items not in this list will be excluded from the results). 

The resultant array contains match scores and a list of matches for each item that had a non-zero number of word matches. It is sorted in descending order by match score.

Example usage:

```php
// functions to search through inverted indices
require 'iSearch.php';

// string of words to search for
$qString = "sky flower nature";

// list of words that have an entry in the inverted index
$wordlist = unserialize(file_get_contents('db/_wordlist.dat'));

// convert the query string into an array of words
$qArray = \iSearch\stringToArray($qString);

// look up the query words in the wordlist, with fuzzy search enabled
$searchWords = \iSearch\getSearchWords($qArray, $wordlist, 85);

// now $searchWords contains the terms for which we need to look up the inverted indices
$iIndex = [];
foreach($searchWords as $word){
	$filename = 'db/' . base64_encode($word) . '.dat';
	if(file_exists($filename)){
		$iIndex[$word] = unserialize(file_get_contents($filename));
	}
}

// we can now simply collate information from the inverted index to obtain our search results
$searchResults = \iSearch\getSearchResults($iIndex, $searchWords);

print_r($searchResults);

/* Output:

Array
(
    [img8] => Array
        (
            [score] => 100
            [matches] => Array
                (
                    [0] => nature
                    [1] => flower
                    [2] => sky
                )

        )

    [img6] => Array
        (
            [score] => 66.67
            [matches] => Array
                (
                    [0] => nature
                    [1] => sky
                )

        )

    [img1] => Array
        (
            [score] => 33.33
            [matches] => Array
                (
                    [0] => nature
                )

        )

    [img3] => Array
        (
            [score] => 33.33
            [matches] => Array
                (
                    [0] => nature
                )

        )

    [img5] => Array
        (
            [score] => 33.33
            [matches] => Array
                (
                    [0] => nature
                )

        )

    [img2] => Array
        (
            [score] => 33.33
            [matches] => Array
                (
                    [0] => flower
                )

        )

)
*/

```
(this output was simplified in usage example B above, with the word matches omited)

## Notes

* Full-text search
    * iSearch works pretty well for full-text search, especially in English. Simply run your documents through `stringToArray()`, strip out stop words and generate an inverted index from the resultant data.
 

## Requirements
* [Supported versions](https://www.php.net/supported-versions.php) of PHP. As of writing that's 8.1+. iSearch almost certainly works on older versions of PHP, but is not tested on those.


----

Documentation updated: `2024-05-06`
