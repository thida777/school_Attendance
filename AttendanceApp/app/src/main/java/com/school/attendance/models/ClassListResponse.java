package com.school.attendance.models;

import java.util.ArrayList;
import java.util.List;

/** GET /classes: {"data": [...]}. */
public class ClassListResponse {

    private List<SchoolClass> data;

    public List<SchoolClass> getData() {
        return data == null ? new ArrayList<>() : data;
    }
}
