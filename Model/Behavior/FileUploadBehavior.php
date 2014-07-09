<?php
/**
  * Behavior for file uploads
  * 
  * Example Usage:
  *
  * @example 
  *   var $actsAs = array('FileUpload.FileUpload');
  *
  * @example 
  *   var $actsAs = array(
  *     'FileUpload.FileUpload' => array(
  *       'uploadDir'    => WEB_ROOT . DS . 'files',
  *       'fields'       => array('name' => 'file_name', 'type' => 'file_type', 'size' => 'file_size'),
  *       'allowedTypes' => array('pdf' => array('application/pdf')),
  *       'required'    => false,
  *       'unique' => false //filenames will overwrite existing files of the same name. (default true)
  *       'fileNameFunction' => 'sha1' //execute the Sha1 function on a filename before saving it (default false)
  *     )
  *    )
  *
  *
  * @note: Please review the plugins/file_upload/config/file_upload_settings.php file for details on each setting.
  * @version: since 0.1
  * @author: Nater Kane
  * @link: http://github.com/naterkane
  */
App::import('Vendor', 'FileUpload.uploader');
require_once(dirname(__FILE__) . DS . '..' . DS . '..' . DS . 'Config' . DS . 'FileUploadSettings.php');
class FileUploadBehavior extends ModelBehavior {
  
  /**
    * Uploader is the uploader instance of class Uploader. This will handle the actual file saving.
    */
  var $Uploader = array();
  
	/**
	 * Behavior settings
	 *
	 * @var array
	 */
		public $settings = array();

	/**
	 * Default setting values
	 *
	 * @var array
	 */
		protected $_defaults = array();

	
  function setFileUploadOption(&$Model, $key, $value) {
    $this->options[$Model->alias][$key] = $value;
    $this->Uploader[$Model->alias]->setOption($key, $value);
  }
  
	/**
	 * Setup the behavior and import required classes.
	 *
	 * @param \Model|object $Model Model using the behavior
	 * @param array $settings Settings to override for model.
	 * @return void
	 */
  public function setup(Model $Model, $config = Array()){
	  
    $FileUploadSettings = new FileUploadSettings();
    if(is_array($config)){
      $this->config[$Model->alias] = array_merge($FileUploadSettings->defaults, $config);
    } else {
    	$this->config[$Model->alias] = $this->_defaults;
    }
    
    $uploader_settings = $this->config[$Model->alias];
    $uploader_settings['uploadDir'] = $this->config[$Model->alias]['forceWebroot'] ? WWW_ROOT . $uploader_settings['uploadDir'] : $uploader_settings['uploadDir']; 
    $uploader_settings['fileModel'] = $Model->alias;
    $this->Uploader[$Model->alias] = new Uploader($uploader_settings);
  }
 
  /**
    * beforeSave if a file is found, upload it, and then save the filename according to the settings
    *
    */
  public function beforeSave(Model $Model, $options = Array()){
    if(isset($Model->data[$Model->alias][$this->options[$Model->alias]['fileVar']])){
      $file = $Model->data[$Model->alias][$this->options[$Model->alias]['fileVar']];
      $this->Uploader[$Model->alias]->file = $file;
      
      if($this->Uploader[$Model->alias]->hasUpload()){
        $fileName = $this->Uploader[$Model->alias]->processFile();
        if($fileName){
          $Model->data[$Model->alias][$this->options[$Model->alias]['fields']['name']] = $fileName;
          $Model->data[$Model->alias][$this->options[$Model->alias]['fields']['size']] = $file['size'];
          $Model->data[$Model->alias][$this->options[$Model->alias]['fields']['type']] = $file['type'];
        } else {
          return false; // we couldn't save the file, return false
        }
        unset($Model->data[$Model->alias][$this->options[$Model->alias]['fileVar']]);
      }
      else {
        unset($Model->data[$Model->alias]);
      }
    }
    return $Model->beforeSave();
  }
  
  /**
    * Updates validation errors if there was an error uploading the file.
    * presents the user the errors.
    */
  public function beforeValidate(Model $Model, $options = Array()){
    if(isset($Model->data[$Model->alias][$this->options[$Model->alias]['fileVar']])){
      $file = $Model->data[$Model->alias][$this->options[$Model->alias]['fileVar']];
      $this->Uploader[$Model->alias]->file = $file;
      if($this->Uploader[$Model->alias]->hasUpload()){
        if($this->Uploader[$Model->alias]->checkFile() && $this->Uploader[$Model->alias]->checkType() && $this->Uploader[$Model->alias]->checkSize()){
          $Model->beforeValidate();
        }
        else {
          $Model->validationErrors[$this->options[$Model->alias]['fileVar']] = $this->Uploader[$Model->alias]->showErrors();
        }
      }
      else {
        if(isset($this->options[$Model->alias]['required']) && $this->options[$Model->alias]['required']){
          $Model->validationErrors[$this->options[$Model->alias]['fileVar']] = 'Select file to upload';
        }
      }
    }
    elseif(isset($this->options[$Model->alias]['required']) && $this->options[$Model->alias]['required']){
      $Model->validationErrors[$this->options[$Model->alias]['fileVar']] = 'No File';
    }
    return $Model->beforeValidate();
  }
  
  /**
    * Automatically remove the uploaded file.
    */
  public function beforeDelete(Model $Model, $cascade = true){
    $Model->recursive = -1;
    $data = $Model->read();
    
   $this->Uploader[$Model->alias]->removeFile($data[$Model->alias][$this->options[$Model->alias]['fields']['name']]);
    return $Model->beforeDelete($cascade);
  }
  
}