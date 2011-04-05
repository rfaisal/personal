/*
    Android Asynchronous Http Client
    Copyright (c) 2011 James Smith <james@loopj.com>
    http://loopj.com

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

        http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
*/

package com.loopj.android.http;

import java.io.IOException;

import org.apache.http.HttpEntity;
import org.apache.http.HttpResponse;
import org.apache.http.StatusLine;
import org.apache.http.client.HttpResponseException;
import org.apache.http.entity.BufferedHttpEntity;
import org.apache.http.util.EntityUtils;

import android.os.Handler;
import android.os.Message;
import android.os.Looper;

public class AsyncHttpResponseHandler {
    private static final int SUCCESS_MESSAGE = 0;
    private static final int FAILURE_MESSAGE = 1;
    private static final int START_MESSAGE = 2;
    private static final int FINISH_MESSAGE = 3;

    private Handler handler;

    public AsyncHttpResponseHandler() {
        // Set up a handler to post events back to the correct thread if possible
        if(Looper.myLooper() != null) {
            handler = new Handler(){
                public void handleMessage(Message msg){
                    AsyncHttpResponseHandler.this.handleMessage(msg);
                }
            };
        }
    }


    // Pre-processing of messages (in background thread)
    public void sendResponseMessage(HttpResponse response) {
        StatusLine status = response.getStatusLine();
        if(status.getStatusCode() >= 300) {
            sendFailureMessage(new HttpResponseException(status.getStatusCode(), status.getReasonPhrase()));
        } else {
            try {
                sendSuccessMessage(getResponseBody(response));
            } catch(IOException e) {
                sendFailureMessage(e);
            }
        }
    }

    public void sendSuccessMessage(String responseBody) {
        sendMessage(obtainMessage(SUCCESS_MESSAGE, responseBody));
    }

    public void sendFailureMessage(Throwable e) {
        sendMessage(obtainMessage(FAILURE_MESSAGE, e));
    }

    public void sendStartMessage() {
        sendMessage(obtainMessage(START_MESSAGE, null));
    }

    public void sendFinishMessage() {
        sendMessage(obtainMessage(FINISH_MESSAGE, null));
    }


    // Pre-processing of messages (in original calling thread)
    protected void handleSuccessMessage(String responseBody) {
        onSuccess(responseBody);
    }

    protected void handleFailureMessage(Throwable e) {
        onFailure(e);
    }


    // Utility functions
    protected void handleMessage(Message msg) {
        switch(msg.what) {
            case SUCCESS_MESSAGE:
                handleSuccessMessage((String)msg.obj);
                break;
            case FAILURE_MESSAGE:
                handleFailureMessage((Throwable)msg.obj);
                break;
            case START_MESSAGE:
                onStart();
                break;
            case FINISH_MESSAGE:
                onFinish();
                break;
        }
    }

    protected void sendMessage(Message msg) {
        if(handler != null){
            handler.sendMessage(msg);
        } else {
            handleMessage(msg);
        }
    }

    protected Message obtainMessage(int responseMessage, Object response) {
        Message msg = null;
        if(handler != null){
            msg = this.handler.obtainMessage(responseMessage, response);
        }else{
            msg = new Message();
            msg.what = responseMessage;
            msg.obj = response;
        }
        return msg;
    }

    protected String getResponseBody(HttpResponse response) throws IOException {
        HttpEntity entity = null;
        HttpEntity temp = response.getEntity();
        if(temp != null) {
            entity = new BufferedHttpEntity(temp);
        }

        return EntityUtils.toString(entity);
    }


    // Public callbacks
    public void onStart() {}
    public void onFinish() {}
    public void onSuccess(String content) {}
    public void onFailure(Throwable error) {}
}