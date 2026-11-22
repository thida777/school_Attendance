package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** A subject: {"id": 2, "code": "MAT", "name_en": "Mathematics", "name_km": "..."}. */
public class Subject {

    private int id;
    private String code;

    @SerializedName("name_en")
    private String nameEn;

    @SerializedName("name_km")
    private String nameKm;

    public int getId() { return id; }
    public String getCode() { return code; }

    /** The subject name in the app's language. */
    public String name(boolean khmer) {
        return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : nameEn;
    }
}
