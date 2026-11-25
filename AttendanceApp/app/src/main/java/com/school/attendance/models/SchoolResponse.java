package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** GET /school: the school name the admin set on the website (School settings). */
public class SchoolResponse {

    private String name;

    @SerializedName("name_en")
    private String nameEn;

    @SerializedName("name_km")
    private String nameKm;

    public String getName() { return name; }
    public String getNameEn() { return nameEn; }
    public String getNameKm() { return nameKm; }
}
