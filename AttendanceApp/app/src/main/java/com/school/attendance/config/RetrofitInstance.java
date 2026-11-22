package com.school.attendance.config;

import com.school.attendance.apis.AttendanceAPI;
import com.school.attendance.apis.AuthAPI;
import com.school.attendance.apis.HistoryAPI;
import com.school.attendance.apis.TeachingAPI;

import java.util.concurrent.TimeUnit;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

/**
 * One Retrofit for the whole app (same idea as Retrofitinstance in class).
 * Every request automatically gets:
 *   Accept: application/json         → Laravel answers in JSON, also for errors
 *   Accept-Language: en | km         → messages in the app's language
 *   Authorization: Bearer <token>    → after login
 */
public final class RetrofitInstance {

    private static Retrofit retrofit;

    private RetrofitInstance() {
    }

    public static Retrofit getRetrofitInstance() {
        if (retrofit == null) {
            OkHttpClient client = new OkHttpClient.Builder()
                    .connectTimeout(10, TimeUnit.SECONDS)
                    .readTimeout(20, TimeUnit.SECONDS)
                    .addInterceptor(chain -> {
                        Request.Builder request = chain.request().newBuilder()
                                // "php artisan serve" closes connections quickly; reusing one it already closed
                                // makes a request fail now and then. A fresh connection per request avoids that.
                                .header("Connection", "close")
                                .header("Accept", "application/json")
                                .header("Accept-Language", Config.currentLanguage());
                        String token = Session.getToken();
                        if (token != null) {
                            request.header("Authorization", "Bearer " + token);
                        }
                        return chain.proceed(request.build());
                    })
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(Config.BASE_URL)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build();
        }
        return retrofit;
    }

    public static AuthAPI getAuthApi() {
        return getRetrofitInstance().create(AuthAPI.class);
    }

    public static TeachingAPI getTeachingApi() {
        return getRetrofitInstance().create(TeachingAPI.class);
    }

    public static AttendanceAPI getAttendanceApi() {
        return getRetrofitInstance().create(AttendanceAPI.class);
    }

    public static HistoryAPI getHistoryApi() {
        return getRetrofitInstance().create(HistoryAPI.class);
    }
}
