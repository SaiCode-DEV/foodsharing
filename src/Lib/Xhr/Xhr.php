<?php

namespace Foodsharing\Lib\Xhr;

/**
 * class give you methods to send data to the client for nice outputs.
 *
 * @author raphael
 */
class Xhr
{
    // display simple popup messages after the request
    private array $messages = [];

    // additional script that is executed if the request success
    private string $script = '';

    // status code 1 = will execute the response
    private int $status = 1;

    // data that will be sent to the client and is available under global ajax object (ajax.data)
    private array $data = [];

    /**
     * Add data accessible by client side js code.
     *
     * example:
     *    $xhr->addData('tree_id', 1);
     *    is accessible in global Javascript with:
     *    alert(ajax.data.tree_id); => output is "1".
     *
     * @deprecated Only ActivityXhr BasketXhr and BellXhr are still using this, do not add new usage!
     */
    public function addData(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Method to send everything to the client in the expected format.
     */
    public function send()
    {
        header('content-type: application/json; charset=utf-8');
        $out = [
            'status' => $this->status,
            'data' => $this->data,
            'script' => $this->script
        ];

        if (!empty($this->messages)) {
            $out['msg'] = $this->messages;
        }

        echo json_encode($out, JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
}
