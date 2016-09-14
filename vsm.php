<?php

    $stopwords = array_map('trim', file('stopwords.txt'));
    $maxList = array();
    $matrix = array();
    $docTerms = array();
    $queryVector = array();
    $cosSim = array();
    
    function rank($docColl, $query){
        global $cosSim;
        generateQueryVec($query);
        textProcessing($docColl);
        tfidf();
        dotProd();
        return $cosSim;
    }
    
    
    function generateQueryVec($query){
        global $stopwords, $queryVector;
        //to lower case
        $query = strtolower($query);
        //removing escape count_chars
        $query = stripcslashes($query);
        //removing non alpha numeric chars
        $query = preg_replace('/[^A-Za-z]/', ' ', $query);
        //removing stopwords
        $queryWords = explode(" ", $query);
        $queryWords = array_diff($queryWords, $stopwords);
    
    
        foreach ($queryWords as $word) {
            if(!isset($queryVector[$word])){
                $queryVector[$word] = 1.0;
            }else{
                $queryVector[$word]++;
            }
        }
        
        //calculating tfidf for query
        $tf = 0.0;
        $idf = 0.0;
        $queryLen = count($queryVector);
        foreach ($queryVector as $word => $count) {
            $tf = $count / $queryLen;
            $idf = 1;
            $queryVector[$word] = ($tf * $idf);
        }
    }

        /*this fuction takes an array documents ([doc_id] => [text]), it will remove any non words from the array 
        along with any stop words and return a document word frequency matrix 
        */
        function textProcessing($docColl){
            global $stopwords, $maxList, $matrix, $docTerms, $queryVector, $placeDetails;
            
            foreach ($docColl as $id => $doc) {
                //to lower case
                $doc = strtolower($doc);
                //removing escape count_chars
                $doc = stripcslashes($doc);
                //removing non alpha numeric chars
                $doc = preg_replace('/[^A-Za-z]/', ' ', $doc);

                $words = explode(" ", $doc);
                

                foreach ($words as $w) {
                    
                    if(!isset($queryVector[$w])){
                        continue;
                    }

                    
                    //counting the number of relivant terms in this document 
                    if(!isset($docTerms[$id])){
                        $docTerms[$id] = 1;    
                    }else{
                        $docTerms[$id]++;    
                    }
                    
                    
                    //incrementing the word counts
                    if(!isset($matrix[$w])){
                        $matrix[$w] = array($id => 1);
                        $maxList[$w] = 1;
                    }
                    else{
                        if(!isset($matrix[$w][$id])){
                            $matrix[$w][$id] = 1;
                        }
                        else{
                            $matrix[$w][$id]++;
                            if($maxList[$w] <  $matrix[$w][$id]){
                                $maxList[$w] =  $matrix[$w][$id];
                            }
                        }
                    }
                }
                
            }

        }  
        
        function tfidf(){
            global $docTerms, $matrix;
            $tf = 0;
            $idf = 0;
            $nDocs = count($docTerms);
            
            foreach ($matrix as $word => $weights) {
                $nDocTerm = count($weights);
                foreach ($weights as $doc => $weight) {
                    $tf = $weight / $docTerms[$doc];
                    $idf = 1 + log(($nDocs/$nDocTerm));
                    $matrix[$word][$doc] = ($tf * $idf);
                }
            }
            
        }
        
        function dotProd(){
            global $cosSim, $matrix, $queryVector, $docTerms;
            $queryMod = 0.0;
            $docMod = 0.0;
            $nom = 0.0;
            
            //calculating the modulus of the query
            foreach ($queryVector as $key => $value) {
                $queryMod += pow($value,2);
            }
            $queryMod = sqrt($queryMod);
            

            foreach ($docTerms as $id => $value) {
                foreach ($queryVector as $word => $v) {
                    if(isset($matrix[$word][$id])){
                        $docMod += pow($matrix[$word][$id], 2);   
                        $nom += $matrix[$word][$id] * $v; 
                    }
                }
                $cosSim[$id] = ($nom / ($queryMod * $docMod));
                
            }
            
            arsort($cosSim);
        }
        
        
        
?>