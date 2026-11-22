package com.school.attendance.config;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.school.attendance.R;
import com.school.attendance.activities.LoginActivity;

import java.util.Map;

import okhttp3.ResponseBody;
import retrofit2.Response;

/**
 * Turns a failed answer from Laravel into one sentence for the teacher.
 *   422 {"message": "...", "errors": {"login": ["The phone or password is not correct."]}} → the first error
 *   403 {"message": "The app is for teachers..."}                                          → the message
 *   429 too many tries, 401 session ended                                                   → our own text
 * Laravel already translates its messages (we send Accept-Language), so we show them as they are.
 */
public final class ApiError {

    private ApiError() {
    }

    public static String message(Context context, Response<?> response) {
        int code = response.code();
        if (code == 429) {
            return context.getString(R.string.error_too_many);
        }
        if (code == 401) {
            return context.getString(R.string.error_session);
        }

        String fromServer = readServerMessage(response.errorBody());
        return fromServer != null ? fromServer : context.getString(R.string.error_unknown, code);
    }

    /**
     * 401 = the token no longer works (logged out elsewhere, password changed on another phone…).
     * Forget it and go back to Login with "Your session has ended". Returns true when that happened.
     */
    public static boolean sessionEnded(Activity activity, Response<?> response) {
        if (response.code() != 401 || activity == null) {
            return false;
        }
        Session.clear();
        Intent intent = new Intent(activity, LoginActivity.class);
        intent.putExtra(LoginActivity.EXTRA_MESSAGE, activity.getString(R.string.error_session));
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        activity.startActivity(intent);
        activity.finish();
        return true;
    }

    /** No answer at all: no internet, wrong address, or Laravel is not running. */
    public static String network(Context context) {
        return context.getString(R.string.error_network);
    }

    private static String readServerMessage(ResponseBody body) {
        if (body == null) {
            return null;
        }
        try {
            JsonObject json = JsonParser.parseString(body.string()).getAsJsonObject();

            // A form error: show the first message of the first field
            if (json.has("errors") && json.get("errors").isJsonObject()) {
                for (Map.Entry<String, JsonElement> field : json.getAsJsonObject("errors").entrySet()) {
                    JsonArray messages = field.getValue().getAsJsonArray();
                    if (messages.size() > 0) {
                        return messages.get(0).getAsString();
                    }
                }
            }
            if (json.has("message")) {
                return json.get("message").getAsString();
            }
        } catch (Exception ignored) {
            // Not JSON (e.g. an HTML error page): fall back to the general message
        }
        return null;
    }
}
