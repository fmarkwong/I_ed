<?php
/*
 * Shuffle the elements in an array
 *
 * @param array $inputArray An array to be randomized
 *
 * @return array The randomized array
 */

function my_shuffle($inputArray)
{
    if (! is_array($inputArray)) {
        throw new Exception('Error!  Input is not an array');
    }

    $randomizedArray = [];

    while (count($inputArray) > 0) {
        $randomOffSet = rand(0, count($inputArray) - 1);
        $randomElement = array_splice($inputArray, $randomOffSet, 1)[0]; #extract random element from $inputArray
        $randomizedArray[] = $randomElement;
    }

    return $randomizedArray;
}
