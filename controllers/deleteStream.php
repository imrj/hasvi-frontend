<?php
/*
This is a special class for deleting big streams
Needs to be a background process, as it can take some time to complete
*/

class HD_DeleteLargeStream extends WP_Background_Process {

    /**
     * @var string
     */
    protected $action = 'DeleteLargeStream';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param string $token stream to delete
     *
     * @return mixed
     */
    protected function task( $token ) {
        //start deleting since it's less than 100 items
        global $dynamodb;
        aws_prep();
        
        do {
            $request = [
                'TableName' => aws_getTableName("tableNameIOT"),
                'KeyConditionExpression' => '#hh = :h_id',
                'ExpressionAttributeNames'=> [ '#hh' => 'hash' ],
                'ExpressionAttributeValues' =>  [
                    ':h_id' => ['S' => $token]
                ],
                'Limit' => 100
            ];
            # Add the ExclusiveStartKey if we got one back in the previous response
            if(isset($responseStream) && isset($responseStream['LastEvaluatedKey'])) {
                $request['ExclusiveStartKey'] = $responseStream['LastEvaluatedKey'];
            }

            try { 
                $responseStream = $dynamodb->query($request);
            } catch (Exception $e) {
                outputAWSError($e);
                return false;
            }
            
            //and delete all items in the response
            foreach ($responseStream['Items'] as $key => $StreamIterator) {
                $responseDelItem = $dynamodb->deleteItem([
                    'TableName' => aws_getTableName("tableNameIOT"),
                    'Key' => [
                        'hash' => ['S' => $StreamIterator['hash']['S']],
                        'datetime' => ['N' => $StreamIterator['datetime']['N']]
                        ]
                ]);
            }
            
        } while(isset($responseStream['LastEvaluatedKey'])); 
        
        //delete from database
        $responseDelStream = $dynamodb->deleteItem([
            'TableName' => aws_getTableName("tableNameStream"),
            'Key' => [
                'hash' => ['S' => $token]
                ]
        ]);
        
        return false;
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        // Show notice to user or perform some other arbitrary task...
    }

}

?>
