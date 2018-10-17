<?php

class SearchBot
{
    private static $modelName = '/models/';
    private static $controllerName = '/controllers/';
    private static $testName = '/tests/';
    private static $keySearch = 'read2-dexter';
    private static $ITER_MAX = 7;
    private static $constructFunction = '__construct';

    private $modelFiles = array();
    private $controllerFiles = array();
    private $folderParent;

    public function __constructor($folderParent)
    {
        $this->folderParent = $folderParent;
    }

    /**
     * list all file .php in folder
     *
     * @return array array[0]: model files, array[1]: controller files
     */
    public function getPHPFile($parent, $file)
    {
        $fullPath = $file != '' ? $parent . '/' . $file : $parent;

        $files = array(array(), array());

        if (is_dir($fullPath) && $file != '.' && $file != '..') {
            foreach (scandir($fullPath) as $item) {
                $scanRs = $this->getPHPFile($fullPath, $item);
                $files = array(array_merge($files[0], $scanRs[0]), array_merge($files[1], $scanRs[1]));
            }
        } elseif (strpos($file, '.php') !== false && !$this->isTest($fullPath)) {
            if ($this->isModel($fullPath)) {
                $files[0][] = $fullPath;
            } elseif ($this->isController($fullPath)) {
                $files[1][] = $fullPath;
            }
        }

        return $files;
    }

    /**
     * search content a file
     *
     * @return array name of function
     */
    public function searchInFile($file, $pattern)
    {
        $result = array();

        $handle = fopen($file, 'r');
        if ($handle == null) {
            echo 'can not open file ';
            return $result;
        }

        if (!preg_match_all($pattern, file_get_contents($file), $matches)) {
            return $result;
        }

        if (strpos($pattern, self::$constructFunction) !== false) {
            return $result;
        }

        $functionName = '';
        $structOpen = 0;
        while (($line = fgets($handle)) !== false) {
            if ($functionName === '' && preg_match('/function\s+(.*)\(.*\)\s*/', $line, $matches)) {
                if (strpos($line, '{') === false) {
                    $line = fgets($handle);
                    if (strpos($line, '{') === false) {
                        continue;
                    }
                }
                $functionName = $matches[1];
                $structOpen++;
                continue;
            }
            if ($functionName === '') {
                continue;
            }
            if (preg_match($pattern, $line) === 1) {
                //found!!!
                $result[] = $functionName;
            }
            if (strpos($line, '{')) {
                $structOpen++;
            }
            if (strpos($line, '}')) {
                $structOpen--;
            }
            if ($structOpen === 0) {
                $functionName = '';
            }
        }

        return $result;
    }

    /**
     * search recursively in all file
     *
     * @param string $key key word
     * @param string $inFile
     * @param boolean $isFuncName
     *
     * @return array reverse tree format
     */
    public function search($key, $inFile = '', $isFuncName = false, $max = 0)
    {
        $pattern = $this->makePattern($key, $isFuncName);
        echo sprintf("START[%s] searching for pattern: %s\n", $max, $pattern);
        $result = array();
        $subResult = null;
        if ($max <= self::$ITER_MAX && ($inFile == '' || $this->isModel($inFile))) {
            foreach (array_merge($this->modelFiles, $this->controllerFiles) as $file) {
                $funcMatches = $this->searchInFile($file, $pattern);
                if (!empty($funcMatches)) {
                    echo sprintf("searching for pattern: %s\n", $pattern);
                    echo $file . ' => ' . json_encode($funcMatches) . "\n";
                    foreach ($funcMatches as $funcMatch) {
                        $subResult = $subResult == null ? array() : $subResult;
                        $subResult = array_merge($subResult, $this->search($funcMatch, $file, true, $max + 1));
                    }
                }
            }
        }
        $result[$this->makeKey($inFile, $key)] = $subResult;

        return $result;
    }

    public function makeKey($file, $key)
    {
        return $file == '' ? $key : $file . '->' . $key;
    }

    public function splitKey($key)
    {
        return explode('->', $key);
    }

    public function isController($filePath)
    {
        return $filePath != '' && strpos($filePath, self::$controllerName) !== false;
    }

    public function isModel($filePath)
    {
        return $filePath != '' && strpos($filePath, self::$modelName) !== false;
    }

    public function isTest($filePath)
    {
        return $filePath != '' && strpos($filePath, self::$testName) !== false;
    }

    public function makePattern($key, $isFuncName = false)
    {
        if (!$isFuncName) {
            return '/' . $key . '/';
        }
        return '/(->|=)\s*' . $key . '\(/';
    }

    public function treeToArray($note, $subTree)
    {
        if ($subTree === null || empty($subTree)) {
            return array($this->splitKey($note));
        }
        $result = array();
        foreach ($subTree as $childNode => $childTree) {
            $arr = $this->treeToArray($childNode, $childTree);
            foreach ($arr as $item) {
                $item = array_merge($this->splitKey($note), $item);
                $result[] = $item;
            }
        }
        return $result;
    }

    public function writeCSVFile($resultArr)
    {
        $fileName = 'result-aslan.csv';
        $fo = fopen($fileName, 'w');
        if ($resultArr != null) {
            foreach ($resultArr as $item) {
                fputcsv($fo, $item);
            }
        }
        fclose($fo);
    }

    public function main()
    {
        echo "list files ...\n";
        $files = $this->getPHPFile($this->folderParent, '');
        $this->modelFiles = $files[0];
        $this->controllerFiles = $files[1];
        echo sizeof($this->modelFiles) . ' ' . sizeof($this->controllerFiles);

        echo "\nseaching ...\n";
        $tree = $this->search(self::$keySearch, '', false);
        $result = $this->treeToArray(self::$keySearch, $tree[self::$keySearch]);

        echo "write file ...\n";
        $this->writeCSVFile($result);

        return $result;
    }
}

$searchBot = new SearchBot('applications/api');
// echo json_encode(searchInFile('applications/api///models/new_segments_research_answers_model.php', '/read2-dexter/')) . "\n";
echo json_encode($searchBot->main()) . "\n";
// echo json_encode($searchBot->treeToArray(
//     'root',
//     array(
//         'a' => array('c' => null, 'd' => array('e' => null)),
//         'b' => null,
//     )
// ));
