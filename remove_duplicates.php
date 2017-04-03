<?php
/*
 * Remove duplicate values from an array
 *
 * @param array $inputArray Array of values that may have duplicates 
 *
 * @return array Array with duplicates removed 
 */

function remove_duplicates($inputArray)
{
    if (! is_array($inputArray)) {
        throw new Exception('Error!  Input is not an array');
    }

    $dedupedArray = []; # the final result array with duplicates removed
    $uniques = [];   # store of each unique element to compare against duplicates

    foreach($inputArray as $element) {
        if (! array_key_exists($element, $uniques)) { # if element not a duplicate
            $dedupedArray[] = $element; # add to result array
            $uniques[$element] = true; # mark element as unique
        }
    }
    return $dedupedArray;
}
