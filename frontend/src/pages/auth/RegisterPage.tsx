import type { ReactNode } from 'react'
import { useState } from 'react'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormProvider, useForm } from 'react-hook-form'
import { Link as RouterLink, Navigate } from 'react-router-dom'
import { z } from 'zod'

import { FormDateField } from '../../components/forms/FormDateField'
import { FormFileField } from '../../components/forms/FormFileField'
import { FormTextField } from '../../components/forms/FormTextField'
import { useAuth } from '../../features/auth/AuthContext'
import { submitMembershipApplication } from '../../services/membership'
import { getErrorMessage } from '../../utils/errors'
import { AuthShell } from './AuthShell'

const registerSchema = z
  .object({
    fullName: z.string().min(2, 'Enter your full name.'),
    email: z.string().email('Enter a valid email address.'),
    phone: z.string().min(10, 'Enter a valid phone number.'),
    address: z.string().min(3, 'Enter your residential address.'),
    dateOfBirth: z
      .string()
      .min(1, 'Select your date of birth.')
      .refine((value) => new Date(value).getTime() < Date.now(), 'Date of birth must be in the past.'),
    occupation: z.string().min(2, 'Enter your occupation.'),
    department: z.string().optional(),
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    confirmPassword: z.string(),
    profilePhoto: z.instanceof(File, { message: 'Upload a profile photo.' }),
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: 'Passwords do not match.',
    path: ['confirmPassword'],
  })

type RegisterFormValues = z.infer<typeof registerSchema>

export function RegisterPage(): ReactNode {
  const { user } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [submitted, setSubmitted] = useState(false)

  const methods = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      fullName: '',
      email: '',
      phone: '',
      address: '',
      dateOfBirth: '',
      occupation: '',
      department: '',
      password: '',
      confirmPassword: '',
    },
  })

  if (user) {
    return <Navigate to="/member" replace />
  }

  const onSubmit = methods.handleSubmit(async (values) => {
    setSubmitting(true)
    setError(null)
    try {
      const formData = new FormData()
      formData.append('full_name', values.fullName)
      formData.append('email', values.email)
      formData.append('password', values.password)
      formData.append('password_confirmation', values.confirmPassword)
      formData.append('phone', values.phone)
      formData.append('address', values.address)
      formData.append('date_of_birth', values.dateOfBirth)
      formData.append('occupation', values.occupation)
      if (values.department) {
        formData.append('department', values.department)
      }
      formData.append('profile_photo', values.profilePhoto)
      await submitMembershipApplication(formData)
      setSubmitted(true)
    } catch (unknownError) {
      setError(getErrorMessage(unknownError, 'Unable to submit your application. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  })

  return (
    <AuthShell
      title="Create an account"
      subtitle="Join the cooperative and access member services."
      footer={
        <>
          Already have an account?{' '}
          <Link component={RouterLink} to="/login" sx={{ color: 'white', fontWeight: 700 }}>
            Sign in
          </Link>
        </>
      }
    >
      {submitted ? (
        <Box sx={{ textAlign: 'center', py: 2 }}>
          <Alert severity="success" sx={{ mb: 2 }}>
            Your membership application has been submitted successfully.
          </Alert>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            The cooperative&apos;s administration will review your application. You will be able to sign in once your
            account is activated.
          </Typography>
          <Button variant="contained" component={RouterLink} to="/login">
            Go to sign in
          </Button>
        </Box>
      ) : (
        <FormProvider {...methods}>
          <Box component="form" onSubmit={onSubmit} noValidate sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            {error ? <Alert severity="error">{error}</Alert> : null}

            <FormTextField<RegisterFormValues> name="fullName" label="Full name" autoComplete="name" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="email" label="Email address" autoComplete="email" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="phone" label="Phone number" autoComplete="tel" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="address" label="Residential address" autoComplete="street-address" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="occupation" label="Occupation" autoComplete="organization-title" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="department" label="Department (optional)" disabled={submitting} />
            <FormDateField<RegisterFormValues> name="dateOfBirth" label="Date of birth" disabled={submitting} />
            <FormFileField<RegisterFormValues> name="profilePhoto" label="Upload profile photo" />
            <FormTextField<RegisterFormValues> name="password" label="Password" type="password" autoComplete="new-password" disabled={submitting} />
            <FormTextField<RegisterFormValues> name="confirmPassword" label="Confirm password" type="password" autoComplete="new-password" disabled={submitting} />

            <Button type="submit" variant="contained" size="large" disabled={submitting} sx={{ mt: 1 }}>
              {submitting ? 'Submitting…' : 'Submit application'}
            </Button>

            <Box component="p" sx={{ m: 0 }}>
              <Typography variant="caption" color="text.secondary">
                Your membership application will be reviewed and approved by the cooperative&apos;s administration before
                your account is activated.
              </Typography>
            </Box>
          </Box>
        </FormProvider>
      )}
    </AuthShell>
  )
}