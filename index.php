<form action="" method="POST" enctype="multipart/form-data">
    Select image to upload:
    <input type="file" name="image" id="imaqe">
    <input type="submit" value="Upload Image" name="submit">
</form>

<?php
/**----------------------------------------------------------------------------------
* Microsoft Developer & Platform Evangelism
*
* Copyright (c) Microsoft Corporation. All rights reserved.
*
* THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, 
* EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE IMPLIED WARRANTIES 
* OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR PURPOSE.
*----------------------------------------------------------------------------------
* The example companies, organizations, products, domain names,
* e-mail addresses, logos, people, places, and events depicted
* herein are fictitious.  No association with any real company,
* organization, product, domain name, email address, logo, person,
* places, or events is intended or should be inferred.
*----------------------------------------------------------------------------------
**/

/** -------------------------------------------------------------
# Azure Storage Blob Sample - Demonstrate how to use the Blob Storage service. 
# Blob storage stores unstructured data such as text, binary data, documents or media files. 
# Blobs can be accessed from anywhere in the world via HTTP or HTTPS. 
#
# Documentation References: 
#  - Associated Article - https://docs.microsoft.com/en-us/azure/storage/blobs/storage-quickstart-blobs-php 
#  - What is a Storage Account - http://azure.microsoft.com/en-us/documentation/articles/storage-whatis-account/ 
#  - Getting Started with Blobs - https://azure.microsoft.com/en-us/documentation/articles/storage-php-how-to-use-blobs/
#  - Blob Service Concepts - http://msdn.microsoft.com/en-us/library/dd179376.aspx 
#  - Blob Service REST API - http://msdn.microsoft.com/en-us/library/dd135733.aspx 
#  - Blob Service PHP API - https://github.com/Azure/azure-storage-php
#  - Storage Emulator - http://azure.microsoft.com/en-us/documentation/articles/storage-use-emulator/ 
#
**/
require_once 'vendor/autoload.php';
require_once "./random_string.php";

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

$connectionString = "DefaultEndpointsProtocol=https;AccountName=".getenv('ACCOUNT_NAME').";AccountKey=".getenv('ACCOUNT_KEY');

// Create blob client.
$blobClient = BlobRestProxy::createBlobService($connectionString);


if(isset($_FILES['image'])){
    $errors= array();
    $file_name = $_FILES['image']['name'];
    $file_size = $_FILES['image']['size'];
    $fileToUpload = $_FILES['image']['tmp_name'];
    $file_type=$_FILES['image']['type'];
    $file_ext=strtolower(end(explode('.',$_FILES['image']['name'])));
      
    $extensions= array("jpeg","jpg","png");
      
    if(in_array($file_ext,$extensions)=== false){
        $errors[]="extension not allowed, please choose a JPEG or PNG file.";
    }
      
    if($file_size > 2097152){
        $errors[]='File size must be excately 2 MB';
    }
      
    if(empty($errors)==true){
        // Create container options object.
		$createContainerOptions = new CreateContainerOptions();

		// Set public access policy. Possible values are
		// PublicAccessType::CONTAINER_AND_BLOBS and PublicAccessType::BLOBS_ONLY.
		// CONTAINER_AND_BLOBS:
		// Specifies full public read access for container and blob data.
		// proxys can enumerate blobs within the container via anonymous
		// request, but cannot enumerate containers within the storage account.
		//
		// BLOBS_ONLY:
		// Specifies public read access for blobs. Blob data within this
		// container can be read via anonymous request, but container data is not
		// available. proxys cannot enumerate blobs within the container via
		// anonymous request.
		// If this value is not specified in the request, container data is
		// private to the account owner.
		$createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);

		// Set container metadata.
		$createContainerOptions->addMetaData("key1", "value1");
		$createContainerOptions->addMetaData("key2", "value2");

		// $containerName = "blockblobs".generateRandomString();
        $containerName = "image";
		try {
			// Create container.
			//$blobClient->createContainer($containerName, $createContainerOptions);

			// Getting local file so that we can upload it to Azure
			$myfile = fopen($fileToUpload, "r") or die("Unable to open file!");
			$data = fread($myfile, filesize($fileToUpload));
			fclose($myfile);
        
			# Upload file as a block blob        
			$content = fopen($fileToUpload, "r");

			//Upload blob
			$blobClient->createBlockBlob($containerName, $fileToUpload, $content);

			// List blobs.
			$listBlobsOptions = new ListBlobsOptions();
			// $listBlobsOptions->setprefix("D");

			echo "These are the blobs present in the container: ";
			echo "<br />";

			do{
				$result = $blobClient->listBlobs($containerName, $listBlobsOptions);
				foreach ($result->getBlobs() as $blob)
				{
					$imageUrl = $blob->getUrl();
					?>
					<img src ="<?php echo $imageUrl ?>" width="200" height="200">
					<?php
				
					// AZURE VISION
					$ocpApimSubscriptionKey = 'e77ddac98acb43048218669cf966a0db';

					// You must use the same location in your REST call as you used to obtain
					// your subscription keys. For example, if you obtained your subscription keys
					// from westus, replace "westcentralus" in the URL below with "westus".
					$uriBase = 'https://southeastasia.api.cognitive.microsoft.com/vision/v2.0/analyze';

					require_once 'HTTP/Request2.php';

					$request = new Http_Request2($uriBase);
					$url = $request->getUrl();

					$headers = array(
					// Request headers
					'Content-Type' => 'application/json',
					'Ocp-Apim-Subscription-Key' => $ocpApimSubscriptionKey
					);
					$request->setHeader($headers);

					$parameters = array(
					// Request parameters
					'visualFeatures' => 'Categories,Description',
					'details' => '',
					'language' => 'en'
					);
					$url->setQueryVariables($parameters);

					$request->setMethod(HTTP_Request2::METHOD_POST);

					// Request body parameters
					$body = json_encode(array('url' => $imageUrl));

					// Request body
					$request->setBody($body);

					try
					{
						$response = $request->send();
						$myArray = json_decode($response->getBody(),true);
						// print text captions only
						echo "<pre>" . $myArray['description']['captions'][0]['text'] . "</pre>"; 
						// this code below to print all JSON
						// echo "<pre>" . json_encode(json_decode($response->getBody()), JSON_PRETTY_PRINT) . "</pre>";
					}
					catch (HttpException $ex)
					{
						echo "<pre>" . $ex . "</pre>";
					}
					//END OF AZURE VISION
		        }
        
				$listBlobsOptions->setContinuationToken($result->getContinuationToken());
			} while($result->getContinuationToken());
			echo "<br />";
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
		}
		catch(InvalidArgumentTypeException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
		}
		 
        echo "Success";
	}else{
        print_r($errors);
    }
}

?>


