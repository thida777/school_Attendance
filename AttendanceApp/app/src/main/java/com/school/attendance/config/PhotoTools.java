package com.school.attendance.config;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Matrix;
import android.media.ExifInterface;
import android.net.Uri;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;

import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.RequestBody;

/**
 * Prepares a picture from the gallery or camera for upload.
 * A phone photo can be 4–8 MB, but the server accepts up to 2 MB, so we shrink it
 * to at most 1024 px, turn it upright, and save it as JPEG. The result is usually 100–300 KB.
 * Run this on a background thread: it reads and resizes a file.
 */
public final class PhotoTools {

    private static final int MAX_SIDE = 1024;

    private PhotoTools() {
    }

    /** Returns the "photo" form field for POST /me/photo, or null when the picture cannot be read. */
    public static MultipartBody.Part prepare(Context context, Uri uri) {
        byte[] jpeg = shrink(context, uri);
        if (jpeg == null) return null;
        RequestBody body = RequestBody.create(MediaType.parse("image/jpeg"), jpeg);
        return MultipartBody.Part.createFormData("photo", "photo.jpg", body);
    }

    private static byte[] shrink(Context context, Uri uri) {
        try {
            // 1. Read only the size, to choose how much to scale down while reading
            BitmapFactory.Options bounds = new BitmapFactory.Options();
            bounds.inJustDecodeBounds = true;
            try (InputStream in = context.getContentResolver().openInputStream(uri)) {
                BitmapFactory.decodeStream(in, null, bounds);
            }
            if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null;

            int sample = 1;
            while (Math.max(bounds.outWidth, bounds.outHeight) / (sample * 2) >= MAX_SIDE) {
                sample *= 2;
            }

            // 2. Read the picture, already smaller
            BitmapFactory.Options options = new BitmapFactory.Options();
            options.inSampleSize = sample;
            Bitmap bitmap;
            try (InputStream in = context.getContentResolver().openInputStream(uri)) {
                bitmap = BitmapFactory.decodeStream(in, null, options);
            }
            if (bitmap == null) return null;

            // 3. Camera photos are often saved sideways with a "turn me" note (EXIF): turn them upright
            int degrees = 0;
            try (InputStream in = context.getContentResolver().openInputStream(uri)) {
                if (in != null) {
                    int orientation = new ExifInterface(in).getAttributeInt(
                            ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL);
                    if (orientation == ExifInterface.ORIENTATION_ROTATE_90) degrees = 90;
                    else if (orientation == ExifInterface.ORIENTATION_ROTATE_180) degrees = 180;
                    else if (orientation == ExifInterface.ORIENTATION_ROTATE_270) degrees = 270;
                }
            } catch (Exception ignored) {
                // No EXIF information: keep it as it is
            }

            // 4. Final size: longest side at most 1024 px
            float scale = Math.min(1f, (float) MAX_SIDE / Math.max(bitmap.getWidth(), bitmap.getHeight()));
            Matrix matrix = new Matrix();
            matrix.postScale(scale, scale);
            matrix.postRotate(degrees);
            Bitmap ready = Bitmap.createBitmap(bitmap, 0, 0, bitmap.getWidth(), bitmap.getHeight(), matrix, true);

            ByteArrayOutputStream out = new ByteArrayOutputStream();
            ready.compress(Bitmap.CompressFormat.JPEG, 85, out);
            return out.toByteArray();
        } catch (Exception e) {
            return null;
        }
    }
}
