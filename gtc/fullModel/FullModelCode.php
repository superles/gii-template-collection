<?php
Yii::import('system.gii.generators.model.ModelCode');

class FullModelCode extends ModelCode {

    public function init() {
        parent::init();

        // Make sure that the CSaveRelationsBehavior is in the application
        // components folder. If it is not, copy it over there.

        $extPath = Yii::getPathOfAlias('ext');
        $modelsPath = Yii::getPathOfAlias('application.models');
        if ($extPath === false)
            mkdir($extPath);

        if (!is_dir($extPath) || !is_writable($extPath))
            throw new CException("Fatal Error: Your application extensions/ is not a writable directory!");

        if (!is_dir($modelsPath) || !is_writable($modelsPath))
            throw new CException("Fatal Error: Your application extensions/ is not a writable directory!");

        $fileNames = scandir($extPath);
        if (!in_array('CSaveRelationsBehavior.php', $fileNames)) {
            $gtcPath = Yii::getPathOfAlias('ext.gtc.vendors.CSaveRelationsBehavior');
            if (!copy($gtcPath . '/CSaveRelationsBehavior.php', $extPath . '/CSaveRelationsBehavior.php'))
                throw new CException('CSaveRelationsBehavior.php could not be copied over to your extensions/ directory.');
        }

        $fileNames = scandir($modelsPath);
        if (!in_array('GtcActiveRecord.php', $fileNames)) {
            $modelPath = Yii::getPathOfAlias('ext.gtc.vendors');
            if (!copy($modelPath . '/GtcActiveRecord.php', $modelsPath . '/GtcActiveRecord.php'))
                throw new CException('GtcActiveRecord.php could not be copied over to your extensions/ directory.');
        }
    }

    public function prepare() {
        parent::prepare();

        $templatePath = $this->templatePath;

        if (($pos = strrpos($this->tableName, '.')) !== false) {
            $schema = substr($this->tableName, 0, $pos);
            $tableName = substr($this->tableName, $pos + 1);
        } else {
            $schema = '';
            $tableName = $this->tableName;
        }
        if ($tableName[strlen($tableName) - 1] === '*') {
            $tables = Yii::app()->db->schema->getTables($schema);
            if ($this->tablePrefix != '') {
                foreach ($tables as $i => $table) {
                    if (strpos($table->name, $this->tablePrefix) !== 0)
                        unset($tables[$i]);
                }
            }
        }
        else
            $tables=array($this->getTableSchema($this->tableName));

        $this->relations = $this->generateRelations();

        foreach ($tables as $table) {
            $tableName = $this->removePrefix($table->name);
            $className = $this->generateClassName($table->name);
            $params = array(
                'tableName' => $schema === '' ? $tableName : $schema . '.' . $tableName,
                'modelClass' => $className,
                'columns' => $table->columns,
                'labels' => $this->generateLabels($table),
                'rules' => $this->generateRules($table),
                'relations' => isset($this->relations[$className]) ? $this->relations[$className] : array(),
            );
            $this->files[] = new CCodeFile(
                            Yii::getPathOfAlias($this->modelPath) . '/' . 'Base' . $className . '.php',
                            $this->render($templatePath . '/basemodel.php', $params)
            );
        }
    }

    public function requiredTemplates() {
        return array(
            'model.php',
            'basemodel.php',
        );
    }

    public function generateRules($table)
    {
            $rules=array();
            $required=array();
            $null=array();
            $integers=array();
            $numerical=array();
            $length=array();
            $safe=array();
            foreach($table->columns as $column)
            {
                    if($column->isPrimaryKey && $table->sequenceName!==null)
                            continue;
                    $r=!$column->allowNull && $column->defaultValue===null;
                    if($r)
                            $required[]=$column->name;
                    else
                            $null[]=$column->name;
                    if($column->type==='integer')
                            $integers[]=$column->name;
                    else if($column->type==='double')
                            $numerical[]=$column->name;
                    else if($column->type==='string' && $column->size>0)
                            $length[$column->size][]=$column->name;
                    else if(!$column->isPrimaryKey && !$r)
                            $safe[]=$column->name;
            }
            if($required!==array())
                    $rules[]="array('".implode(', ',$required)."', 'required')";
            if($null!==array())
                    $rules[]="array('".implode(', ',$null)."', 'default', 'setOnEmpty' => true, 'value' => null)";
            if($integers!==array())
                    $rules[]="array('".implode(', ',$integers)."', 'numerical', 'integerOnly'=>true)";
            if($numerical!==array())
                    $rules[]="array('".implode(', ',$numerical)."', 'numerical')";
            if($length!==array())
            {
                    foreach($length as $len=>$cols)
                            $rules[]="array('".implode(', ',$cols)."', 'length', 'max'=>$len)";
            }
            if($safe!==array())
                    $rules[]="array('".implode(', ',$safe)."', 'safe')";

            return $rules;
    }

}

?>